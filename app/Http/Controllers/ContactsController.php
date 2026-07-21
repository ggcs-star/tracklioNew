<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContactsController extends Controller
{
    public function index(Request $request)
{
    try {

        $userId = (string) Auth::id();

        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');

        $query = Contact::where('user_id', $userId);

            if (!empty($search)) {

                $query->where(function ($q) use ($search) {

                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");

                });

            }

            $contacts = $query
                ->latest()
                ->paginate($perPage);

        $contacts->getCollection()->transform(function ($c) {
            return [
                '_id' => (string)$c->_id,
                'name' => $c->name,
                'phone_number' => $c->phone_number,
                'opt_in' => (bool)$c->opt_in,
                'source' => $c->source ?? 'manual',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $contacts,
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ],500);

    }
}

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name'         => 'nullable|string|max:255',
                'phone_number' => 'required|string',
                'opt_in'       => 'required|boolean',
            ]);

            $phone = $this->normalizePhone($request->phone_number);

            $contact = Contact::create([
                'user_id'      => (string) Auth::id(),
                'name'         => $request->name,
                'phone_number' => $phone,
                'opt_in'       => $request->opt_in,
                'source'       => 'manual',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contact added successfully',
                'data'    => [
                    '_id'          => (string) $contact->_id,
                    'name'         => $contact->name,
                    'phone_number' => $contact->phone_number,
                    'opt_in'       => (bool) $contact->opt_in,
                    'source'       => $contact->source,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Contact store error', [
                'user_id' => Auth::id(),
                'payload' => $request->all(),
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add contact',
            ], 500);
        }
    }
public function uploadContacts(Request $request)
{
    $request->validate([
        'file' => 'required|file|max:8192',
    ]);

    $file = $request->file('file');
    $ext  = strtolower($file->getClientOriginalExtension());

    if ($ext === 'csv') {
        return $this->uploadCsv($file);
    }

    if ($ext === 'vcf') {
        return $this->uploadVcf($file);
    }

    return back()->with('error', 'Only CSV or VCF files supported');
}
private function uploadCsv($file)
{
    set_time_limit(0);

    $userId = (string) auth()->id();

    $handle = fopen($file->getRealPath(), 'r');

    if (!$handle) {
        return back()->with('error', 'Unable to read CSV');
    }

    $header = fgetcsv($handle);

    $header = array_map(function ($h) {
        return strtolower(trim($h));
    }, $header);

    $nameIndex  = array_search('recruiter_name', $header);
    $phoneIndex = array_search('mobile', $header);

    if ($phoneIndex === false) {
        fclose($handle);
        return back()->with('error', 'Mobile column not found');
    }

    $batch = [];
    $inserted = 0;

    while (($row = fgetcsv($handle)) !== false) {

        $name = $nameIndex !== false
            ? trim($row[$nameIndex] ?? '')
            : null;

        $phone = trim($row[$phoneIndex] ?? '');

        if ($phone == '') {
            continue;
        }

        // remove scientific notation issues
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) == 10) {
            $phone = '91' . $phone;
        }

        $batch[] = [
            'user_id'      => $userId,
            'name'         => $name,
            'phone_number' => $phone,
            'opt_in'       => true,
            'source'       => 'csv',
            'created_at'   => now(),
            'updated_at'   => now(),
        ];

        $inserted++;

        if (count($batch) >= 500) {
            Contact::insert($batch);
            $batch = [];
        }
    }

    fclose($handle);

    if (!empty($batch)) {
        Contact::insert($batch);
    }

    return back()->with(
        'success',
        "{$inserted} contacts imported successfully."
    );
}
private function uploadVcf($file)
{
    set_time_limit(0);

    $content = file_get_contents($file->getRealPath());

    // unfold folded lines
    $content = preg_replace("/\r\n[ \t]/", '', $content);

    $cards = preg_split('/END:VCARD/i', $content);

    $userId = (string) auth()->id();

    $batch = [];
    $inserted = 0;

    foreach ($cards as $card) {
        if (!trim($card)) continue;

        // ---- NAME ----
        preg_match('/^FN:(.+)$/mi', $card, $fn);

        if (empty($fn)) {
            preg_match('/^N:(.+)$/mi', $card, $n);
            if (!empty($n[1])) {
                $p = explode(';', $n[1]);
                $fn[1] = trim(($p[1] ?? '') . ' ' . ($p[0] ?? ''));
            }
        }

        $name = isset($fn[1]) ? trim($fn[1]) : null;

        // ---- ALL TEL ----
        preg_match_all('/TEL[^:]*:(.+)/i', $card, $tels);

        if (empty($tels[1])) continue;

        foreach ($tels[1] as $rawPhone) {

            $rawPhone = trim($rawPhone);
            if ($rawPhone === '') continue;

            // KEEP RAW VALUE (NO COLLISION)
            $phone = $rawPhone;

            $batch[] = [
                'user_id'      => $userId,
                'name'         => $name,
                'phone_number' => $phone,
                'opt_in'       => true,
                'source'       => 'vcf',
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            $inserted++;

            if (count($batch) === 500) {
                Contact::insert($batch);
                $batch = [];
            }
        }
    }

    if (!empty($batch)) {
        Contact::insert($batch);
    }

    return back()->with(
        'success',
        "{$inserted} numbers imported (no filtering, no dedupe)"
    );
}


public function listForBroadcast()
{
    return response()->json([
        'success' => true,
        'data' => Contact::where('user_id', (string) auth()->id())
            ->get([
                '_id',
                'name',
                'phone_number'
            ])
            ->map(fn ($c) => [
                'id' => (string) $c->_id,
                'name' => $c->name ?? 'Unknown',
                'phone' => $c->phone_number,
            ])
    ]);
}


private function normalizePhone(string $phone): string
{
    $phone = preg_replace('/\D/', '', $phone);

    // already country code
    if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
        return $phone;
    }

    // Indian local number
    if (strlen($phone) === 10) {
        return '91' . $phone;
    }

    return $phone;
}

}