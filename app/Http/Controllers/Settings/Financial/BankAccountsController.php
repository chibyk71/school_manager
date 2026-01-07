<?php

namespace App\Http\Controllers\Settings\Financial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * BankAccountsController v1.0 – Production-Ready School Bank Accounts Management
 *
 * Purpose:
 * Manages the list of school bank accounts displayed to parents for offline/manual payments
 * (direct bank transfers, cash deposits). Essential for schools where online payments are not
 * the only option (very common in Nigeria and many regions).
 *
 * Why this page is necessary:
 * - Payment Gateways = online card/bank payments
 * - Bank Accounts = offline/manual transfers (parents need account details on invoices/portal)
 * - Multiple accounts common (different banks, different purposes: tuition, hostel, etc.)
 * - Parents must see clear, ordered bank details
 * - Industry standard: Fedena, QuickSchools, Classter all have separate "Bank Accounts" page
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set platform-wide defaults
 * - Full CRUD: add, edit, reorder, delete
 * - Default account selection (shown first)
 * - Responsive PrimeVue DataTable with inline actions
 * - Modal for add/edit
 * - Bulk delete
 * - Production-ready: validation, security, error handling
 *
 * Settings Key: 'financial.bank_accounts'
 *
 * Structure:
 *   'financial.bank_accounts' => [
 *       'accounts' => [
 *           [
 *               'id' => 1,
 *               'bank_name' => 'Guaranty Trust Bank',
 *               'account_name' => 'Dreams International School',
 *               'account_number' => '0123456789',
 *               'branch' => 'Lagos Mainland',
 *               'currency' => 'NGN',
 *               'notes' => 'For tuition fees only',
 *               'is_default' => true,
 *           ],
 *           // ...
 *       ]
 *   ]
 *
 * Fits into the Settings Module:
 * - Route: GET/POST/PUT/DELETE settings.financial.banks
 * - Navigation: Financial → Bank Accounts
 * - Frontend: resources/js/Pages/Settings/Financial/BankAccounts.vue
 */

class BankAccountsController extends Controller
{
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('financial.bank_accounts', $school);

        $settings['accounts'] = $settings['accounts'] ?? [];

        return Inertia::render('Settings/Financial/BankAccounts', [
            'bank_accounts' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Financial'],
                ['label' => 'Bank Accounts'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
            'branch' => 'nullable|string|max:100',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string|max:500',
            'is_default' => 'boolean',
        ]);

        try {
            $current = getMergedSettings('financial.bank_accounts', $school);
            $accounts = $current['accounts'] ?? [];

            if ($validated['is_default']) {
                $accounts = array_map(fn($a) => array_merge($a, ['is_default' => false]), $accounts);
            }

            $accounts[] = [
                'id' => (max(array_column($accounts, 'id') ?: [0]) + 1),
                'bank_name' => $validated['bank_name'],
                'account_name' => $validated['account_name'],
                'account_number' => $validated['account_number'],
                'branch' => $validated['branch'],
                'currency' => strtoupper($validated['currency']),
                'notes' => $validated['notes'],
                'is_default' => $validated['is_default'] ?? false,
            ];

            SaveOrUpdateSchoolSettings('financial.bank_accounts', ['accounts' => $accounts], $school);

            return redirect()
                ->route('settings.financial.banks')
                ->with('success', 'Bank account added successfully.');
        } catch (\Exception $e) {
            Log::error('Bank account add failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to add bank account.');
        }
    }

    public function update(Request $request, $id)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
            'branch' => 'nullable|string|max:100',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string|max:500',
            'is_default' => 'boolean',
        ]);

        try {
            $current = getMergedSettings('financial.bank_accounts', $school);
            $accounts = $current['accounts'] ?? [];

            $found = false;
            foreach ($accounts as &$account) {
                if ($account['id'] == $id) {
                    if ($validated['is_default']) {
                        $accounts = array_map(fn($a) => array_merge($a, ['is_default' => false]), $accounts);
                    }
                    $account = array_merge($account, $validated, ['currency' => strtoupper($validated['currency'])]);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return redirect()->back()->with('error', 'Bank account not found.');
            }

            SaveOrUpdateSchoolSettings('financial.bank_accounts', ['accounts' => $accounts], $school);

            return redirect()
                ->route('settings.financial.banks')
                ->with('success', 'Bank account updated successfully.');
        } catch (\Exception $e) {
            Log::error('Bank account update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update bank account.');
        }
    }

    public function destroy(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $current = getMergedSettings('financial.bank_accounts', $school);
            $accounts = $current['accounts'] ?? [];

            $remaining = array_filter($accounts, fn($a) => !in_array($a['id'], $validated['ids']));

            SaveOrUpdateSchoolSettings('financial.bank_accounts', ['accounts' => array_values($remaining)], $school);

            return redirect()
                ->route('settings.financial.banks')
                ->with('success', 'Bank account(s) deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Bank account delete failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete bank account(s).');
        }
    }
}
