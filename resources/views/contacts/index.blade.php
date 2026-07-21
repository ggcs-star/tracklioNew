@extends('layouts.index')

@section('title', 'Contacts')

@section('content')
<div
    x-data="contactsManager('{{ csrf_token() }}')"
    x-init="fetchContacts()"
    class="w-full px-3 sm:px-4 md:px-6 py-4 md:py-8 space-y-4 md:space-y-8"
>

    <!-- ================= ENHANCED HEADER ================= -->
    <div class="relative overflow-hidden bg-gradient-to-br from-gray-900 via-slate-900 to-gray-950 rounded-xl md:rounded-2xl p-4 sm:p-6 md:p-8 shadow-lg md:shadow-2xl">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 via-purple-500/10 to-cyan-500/10 animate-gradient-x"></div>
        <div class="relative z-10">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 md:gap-6">
                <div class="flex items-center gap-3 md:gap-4">
                    <div class="w-10 h-10 md:w-14 md:h-14 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg md:rounded-xl flex items-center justify-center shadow-md md:shadow-lg">
                        <svg class="w-5 h-5 md:w-7 md:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-bold text-white mb-1 md:mb-2">Contacts</h1>
                        <p class="text-xs md:text-sm text-slate-300">Manage your WhatsApp contacts</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 md:gap-3 w-full md:w-auto mt-4 md:mt-0">
                    <form action="{{ url('/contacts/upload-csv') }}" method="POST" enctype="multipart/form-data">

    @csrf

    <label
        class="cursor-pointer bg-slate-700 hover:bg-slate-800 text-white px-6 py-3 rounded-xl shadow flex items-center gap-2">

        <svg xmlns="http://www.w3.org/2000/svg"
             class="w-5 h-5"
             fill="none"
             viewBox="0 0 24 24"
             stroke="currentColor">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0-12l-4 4m4-4l4 4"/>
        </svg>

        <span>Upload CSV</span>

        <input
            type="file"
            name="file"
            accept=".csv,.vcf"
            class="hidden"
            onchange="this.form.submit()">

    </label>

</form>

                    <!-- ADD CONTACT -->
                    <button
                        @click="openAdd = true"
                        class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-3 sm:px-4 md:px-6 py-2 md:py-3 rounded-lg md:rounded-xl shadow flex items-center justify-center gap-2 md:gap-3 transition-all duration-300 hover:scale-105 hover:shadow-xl group text-sm md:text-base w-full sm:w-auto">
                        <svg class="w-4 h-4 md:w-5 md:h-5 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 4v16m8-8H4"/>
                        </svg>
                        <span class="font-medium truncate">Add Contact</span>
                    </button>
                </div>
            </div>

            <!-- STATS BAR -->
            <div class="flex flex-wrap items-center gap-2 md:gap-4 mt-4 md:mt-8 pt-3 md:pt-6 border-t border-white/10">
                <div class="flex items-center gap-1 md:gap-2">
                    <div class="w-2 h-2 md:w-3 md:h-3 rounded-full bg-emerald-400 animate-pulse"></div>
                    <span class="text-xs md:text-sm text-slate-300">Live contact sync</span>
                </div>
                <div class="hidden sm:flex items-center gap-1 md:gap-2">
                    <svg class="w-3 h-3 md:w-4 md:h-4 text-cyan-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-xs md:text-sm text-slate-300">Updated in real-time</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= RESPONSIVE CONTACT CARDS/TABLE ================= -->
    <div class="bg-white rounded-xl md:rounded-2xl shadow-lg md:shadow-xl overflow-hidden border border-slate-200">
        <div class="px-4 md:px-6 py-3 md:py-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 md:gap-0">
                <div>
                    <h3 class="text-lg md:text-lg font-semibold text-slate-900">All Contacts</h3>
                    <p class="text-xs md:text-sm text-slate-600 mt-1">Total <span x-text="totalContacts" class="font-bold text-indigo-600"></span> contacts
                        <span x-show="searchQuery && filteredContacts.length !== contacts.length" class="text-slate-500">
                            (Showing <span x-text="filteredContacts.length" class="font-semibold"></span> filtered)
                        </span>
                    </p>
                </div>
                
                <!-- DYNAMIC SEARCH INPUT -->
                <div class="relative w-full sm:w-auto">
                    <input
                        type="text"
                        placeholder="Search contacts..."
                        x-model="searchQuery"
                        @input.debounce.500ms="fetchContacts(1)"
                        
                        class="pl-9 md:pl-10 pr-8 md:pr-10 py-2 md:py-2.5 border border-slate-300 rounded-lg md:rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full sm:w-48 md:w-64"
                    >
                    <!-- Clear Search Button -->
                    <button
                        x-show="searchQuery.length > 0"
                        @click="searchQuery=''; fetchContacts(1)"
                        class="absolute right-2 md:right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors p-1"
                        type="button"
                    >
                        <svg class="w-3.5 h-3.5 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <svg class="absolute left-2.5 md:left-3 top-1/2 transform -translate-y-1/2 w-3.5 h-3.5 md:w-4 md:h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- DESKTOP TABLE (Hidden on mobile) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-full">
                <thead class="bg-gradient-to-r from-slate-50 to-slate-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Phone Number</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Opt-In Status</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">Source</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <template x-for="c in filteredContacts" :key="c._id">
                        <tr class="group hover:bg-gradient-to-r hover:from-indigo-50/50 hover:to-purple-50/50 transition-all duration-200">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-100 to-blue-50 flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform">
                                        <span class="font-bold text-blue-600" x-text="(c.name || '?')[0].toUpperCase()"></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900" x-text="c.name || 'Unnamed'"></p>
                                        <p class="text-xs text-slate-500" x-show="!c.name">No name provided</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    <span class="font-mono text-slate-800 text-sm" x-text="c.phone_number"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all duration-300"
                                        :class="c.opt_in
                                            ? 'bg-gradient-to-r from-emerald-100 to-green-100 text-emerald-700 border border-emerald-200'
                                            : 'bg-gradient-to-r from-red-100 to-rose-100 text-red-700 border border-red-200'"
                                        x-text="c.opt_in ? '✅ Opted In' : '❌ Not Opted'">
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full"
                                         :class="c.source === 'manual' ? 'bg-indigo-500' : 
                                                 c.source === 'csv' ? 'bg-cyan-500' : 
                                                 'bg-slate-400'"></div>
                                    <span class="capitalize font-medium text-slate-700 text-sm" x-text="c.source"></span>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredContacts.length === 0">
                        <td colspan="4" class="px-6 py-12 md:py-16 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-16 h-16 md:w-20 md:h-20 bg-gradient-to-br from-slate-100 to-slate-50 rounded-2xl flex items-center justify-center mb-3 md:mb-4 shadow-sm">
                                    <svg class="w-8 h-8 md:w-10 md:h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-base md:text-lg font-semibold text-slate-700 mb-2">
                                    <span x-show="searchQuery">No contacts found</span>
                                    <span x-show="!searchQuery">No contacts yet</span>
                                </h3>
                                <p class="text-slate-500 text-sm md:text-base mb-4 md:mb-6 px-4">
                                    <span x-show="searchQuery">Try a different search term</span>
                                    <span x-show="!searchQuery">Start by adding your first contact</span>
                                </p>
                                <button
                                    @click="searchQuery ? (searchQuery = ''; fetchContacts(1)) : openAdd = true"
                                    class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-4 py-2.5 md:px-5 md:py-2.5 rounded-lg shadow flex items-center gap-2 transition-all duration-300 hover:scale-105 text-sm md:text-base">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <span x-show="searchQuery">Clear Search</span>
                                    <span x-show="!searchQuery">Add First Contact</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- MOBILE CARDS (Visible on mobile only) -->
        <div class="md:hidden">
            <div class="divide-y divide-slate-100">
                <template x-for="c in filteredContacts" :key="c._id">
                    <div class="p-4 hover:bg-gradient-to-r hover:from-indigo-50/50 hover:to-purple-50/50 transition-all duration-200">
                        <div class="flex items-start gap-3">
                            <!-- Contact Avatar -->
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-100 to-blue-50 flex items-center justify-center shadow-sm flex-shrink-0">
                                <span class="font-bold text-blue-600 text-lg" x-text="(c.name || '?')[0].toUpperCase()"></span>
                            </div>
                            
                            <!-- Contact Details -->
                            <div class="flex-1 min-w-0">
                                <!-- Name and Status -->
                                <div class="flex items-start justify-between mb-2">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900 truncate" x-text="c.name || 'Unnamed'"></p>
                                        <p class="text-xs text-slate-500" x-show="!c.name">No name provided</p>
                                    </div>
                                    <span
                                        class="px-2 py-1 rounded-full text-xs font-semibold transition-all duration-300 whitespace-nowrap ml-2"
                                        :class="c.opt_in
                                            ? 'bg-gradient-to-r from-emerald-100 to-green-100 text-emerald-700 border border-emerald-200'
                                            : 'bg-gradient-to-r from-red-100 to-rose-100 text-red-700 border border-red-200'"
                                        x-text="c.opt_in ? 'Opted In' : 'Not Opted'">
                                    </span>
                                </div>
                                
                                <!-- Phone Number -->
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    <span class="font-mono text-slate-800 text-sm truncate" x-text="c.phone_number"></span>
                                </div>
                                
                                <!-- Source -->
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full flex-shrink-0"
                                         :class="c.source === 'manual' ? 'bg-indigo-500' : 
                                                 c.source === 'csv' ? 'bg-cyan-500' : 
                                                 'bg-slate-400'"></div>
                                    <span class="capitalize text-slate-700 text-xs" x-text="c.source"></span>
                                    <span class="text-slate-400 mx-1">•</span>
                                    <span class="text-xs text-slate-500">Contact</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty State Mobile -->
                <div x-show="filteredContacts.length === 0" class="p-8 text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-slate-100 to-slate-50 rounded-2xl flex items-center justify-center mb-4 mx-auto shadow-sm">
                        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-700 mb-2">
                        <span x-show="searchQuery">No contacts found</span>
                        <span x-show="!searchQuery">No contacts yet</span>
                    </h3>
                    <p class="text-slate-500 text-sm mb-6 px-4">
                        <span x-show="searchQuery">Try a different search term</span>
                        <span x-show="!searchQuery">Start by adding your first contact</span>
                    </p>
                    <button
                        @click="searchQuery ? (searchQuery = ''; fetchContacts(1)) : openAdd = true"
                        class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-5 py-2.5 rounded-lg shadow flex items-center gap-2 transition-all duration-300 hover:scale-105 mx-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span x-show="searchQuery">Clear Search</span>
                        <span x-show="!searchQuery">Add First Contact</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- PAGINATION FOOTER -->
        <div class="px-4 md:px-6 py-3 md:py-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-white">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-0">
                <div class="text-xs md:text-sm text-slate-600">
                    Showing <span class="font-semibold text-slate-900" x-text="filteredContacts.length"></span> contacts
                    <span x-show="searchQuery && filteredContacts.length !== contacts.length" class="text-slate-500">
                        (of <span x-text="contacts.length"></span> total)
                    </span>
                </div>
                <div class="flex items-center gap-3">
                <button
                    @click="if(currentPage>1) fetchContacts(currentPage-1)"
                    :disabled="currentPage==1"
                    class="px-3 py-2 border rounded">

                    Previous

                </button>

                <span>

                    Page
                    <span x-text="currentPage"></span>

                    of

                    <span x-text="lastPage"></span>

                </span>

                <button
                    @click="if(currentPage<lastPage) fetchContacts(currentPage+1)"
                    :disabled="currentPage==lastPage"
                    class="px-3 py-2 border rounded">

                    Next

                </button>

            </div>
            </div>
        </div>
    </div>

    <!-- ================= RESPONSIVE ADD CONTACT MODAL ================= -->
    <div
        x-show="openAdd"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-3 sm:p-4"
    >
        <div class="bg-white w-full max-w-md rounded-xl md:rounded-2xl shadow-xl md:shadow-2xl overflow-hidden mx-auto max-h-[90vh] overflow-y-auto">
            <!-- MODAL HEADER -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 md:px-6 py-4 md:py-5">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 md:w-10 md:h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 md:w-5 md:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-lg md:text-xl font-bold text-white truncate">Add New Contact</h2>
                        <p class="text-indigo-100 text-xs md:text-sm truncate">Add a contact to your WhatsApp list</p>
                    </div>
                </div>
            </div>

            <!-- MODAL BODY -->
            <div class="p-4 md:p-6 space-y-4 md:space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Full Name (Optional)</label>
                    <input
                        type="text"
                        placeholder="e.g., John Doe"
                        x-model="form.name"
                        class="w-full px-3 md:px-4 py-2.5 md:py-3 border border-slate-300 rounded-lg md:rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300 hover:border-slate-400 text-sm md:text-base"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Phone Number</label>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2 flex items-center gap-2">
                            <span class="text-slate-500 text-sm md:text-base">+91</span>
                            <div class="w-px h-3 md:h-4 bg-slate-300"></div>
                        </div>
                        <input
                            type="text"
                            placeholder="XXXXXXXXXX"
                            x-model="form.phone_number"
                            class="w-full pl-14 md:pl-16 pr-3 md:pr-4 py-2.5 md:py-3 border border-slate-300 rounded-lg md:rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300 hover:border-slate-400 font-mono text-sm md:text-base"
                        >
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Enter 10-digit phone number without country code</p>
                </div>

                <div class="flex items-center justify-between p-3 md:p-4 bg-gradient-to-r from-slate-50 to-slate-100 rounded-lg md:rounded-xl border border-slate-200">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800 text-sm md:text-base">WhatsApp Opt-In</p>
                        <p class="text-xs text-slate-600">User has consented to receive messages</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-3">
                        <input type="checkbox" x-model="form.opt_in" class="sr-only peer">
                        <div class="w-10 h-5 md:w-12 md:h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 md:after:h-5 md:after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>
            </div>

            <!-- MODAL FOOTER -->
            <div class="px-4 md:px-6 py-4 md:py-5 bg-gradient-to-r from-slate-50 to-white border-t border-slate-200">
                <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
                    <button
                        @click="openAdd = false"
                        class="px-4 md:px-5 py-2.5 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg md:rounded-xl transition-all duration-300 font-medium text-sm md:text-base w-full sm:w-auto">
                        Cancel
                    </button>
                    <button
                        @click="saveContact()"
                        class="px-4 md:px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg md:rounded-xl shadow transition-all duration-300 font-medium flex items-center justify-center gap-2 text-sm md:text-base w-full sm:w-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Contact
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ================= ENHANCED ALPINE SCRIPT ================= -->
<script>
function contactsManager(csrfToken) {
    return {
        contacts: [],
        filteredContacts: [],
        searchQuery: '',
        openAdd: false,
        csrf: csrfToken,
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        totalContacts: 0,

        form: {
            name: '',
            phone_number: '',
            opt_in: true
        },

        fetchContacts(page = 1) {

            fetch(`/contacts?page=${page}&per_page=10&search=${encodeURIComponent(this.searchQuery)}`)
                .then(res => res.json())
                .then(res => {

                    if(res.success){

                        this.contacts = res.data.data;
                        this.filteredContacts = this.contacts;

                        this.currentPage = res.data.current_page;
                        this.lastPage = res.data.last_page;
                        this.totalContacts = res.data.total;

                    }

                });

        },

        // filterContacts() {
        //     if (!this.searchQuery.trim()) {
        //         this.filteredContacts = [...this.contacts];
        //         return;
        //     }

        //     const query = this.searchQuery.toLowerCase().trim();
            
        //     this.filteredContacts = this.contacts.filter(contact => {
        //         // Search in name
        //         if (contact.name && contact.name.toLowerCase().includes(query)) {
        //             return true;
        //         }
                
        //         // Search in phone number
        //         if (contact.phone_number && contact.phone_number.includes(query)) {
        //             return true;
        //         }
                
        //         // Search in source
        //         if (contact.source && contact.source.toLowerCase().includes(query)) {
        //             return true;
        //         }
                
        //         // Search in opt-in status
        //         const optInStatus = contact.opt_in ? 'opted' : 'not opted';
        //         if (optInStatus.includes(query)) {
        //             return true;
        //         }
                
        //         return false;
        //     });
        // },
        filterContacts() {
    this.fetchContacts(1);
},

        saveContact() {
            if (!this.form.phone_number.trim()) {
                this.showToast('Phone number is required', 'error');
                return;
            }

            fetch('/contacts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf
                },
                body: JSON.stringify(this.form)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    this.fetchContacts(this.currentPage);
                    this.openAdd = false;
                    this.form = { name: '', phone_number: '', opt_in: true };
                    this.showToast('Contact added successfully!', 'success');
                } else {
                    this.showToast(res.message || 'Failed to save contact', 'error');
                }
            })
            .catch(() => {
                this.showToast('Something went wrong', 'error');
            });
        },

        showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 left-4 sm:left-auto sm:right-4 z-50 px-4 py-3 rounded-xl shadow-xl text-white font-medium transform transition-all duration-300 translate-x-full sm:translate-x-full ${
                type === 'success' ? 'bg-gradient-to-r from-emerald-500 to-green-500' :
                type === 'error' ? 'bg-gradient-to-r from-red-500 to-rose-500' :
                'bg-gradient-to-r from-blue-500 to-cyan-500'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);

            // Animate in
            setTimeout(() => toast.classList.remove('translate-x-full'), 10);

            // Remove after 3 seconds
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    }
}
</script>

<style>
@keyframes gradient-x {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

.animate-gradient-x {
    background-size: 200% 200%;
    animation: gradient-x 15s ease infinite;
}

/* Enhanced scrollbar for mobile */
.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: linear-gradient(to right, #4f46e5, #7c3aed);
    border-radius: 3px;
}

/* Mobile touch improvements */
@media (max-width: 768px) {
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
    }
    
    /* Better tap targets */
    button, 
    input[type="checkbox"] + div,
    label {
        min-height: 44px;
    }
    
    /* Improve form input touch */
    input, 
    textarea, 
    select {
        font-size: 16px !important; /* Prevents iOS zoom on focus */
    }
    
    /* Modal scroll on small screens */
    .max-h-\[90vh\] {
        max-height: 90vh;
    }
}

/* Responsive font sizes */
@media (max-width: 640px) {
    h1 {
        font-size: 1.5rem;
    }
    
    h2 {
        font-size: 1.25rem;
    }
    
    h3 {
        font-size: 1.125rem;
    }
}

/* Better mobile spacing */
@media (max-width: 768px) {
    .space-y-4 > * + * {
        margin-top: 1rem;
    }
    
    .gap-2 {
        gap: 0.5rem;
    }
}

/* Responsive table improvements */
@media (max-width: 1024px) {
    table {
        min-width: 768px;
    }
}

/* Mobile-safe animations */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Better modal on very small screens */
@media (max-width: 375px) {
    .p-3 {
        padding: 0.75rem;
    }
    
    .px-4 {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}

/* Safe area support for notched phones */
@supports (padding: max(0)) {
    .px-3 {
        padding-left: max(0.75rem, env(safe-area-inset-left));
        padding-right: max(0.75rem, env(safe-area-inset-right));
    }
    
    .py-4 {
        padding-top: max(1rem, env(safe-area-inset-top));
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }
}

/* Loading skeleton for better perceived performance */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}

/* Table row hover effect - mobile safe */
@media (hover: hover) and (pointer: fine) {
    tbody tr:hover {
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
    }
}

/* Smooth transitions for search */
[x-cloak] {
    display: none !important;
}
</style>
@endsection