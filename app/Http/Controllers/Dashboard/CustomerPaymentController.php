<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AccountTransaction;
use App\Models\Customer;
use App\Models\Shop;
use App\Http\Controllers\Controller;
use App\Services\CustomerCreditService;
use App\Services\Ledger\LedgerBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException as BrickMathException;
use Brick\Math\RoundingMode;

class CustomerPaymentController extends Controller
{
    /**
     * Show the form for recording a customer payment (reduces customer balance).
     */
    public function create(Request $request, LedgerBalanceService $balanceService)
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $customers = Customer::query()
            ->where(function ($q) {
                $q->where('is_walkin', false)->orWhere('is_walkin', 0)->orWhereNull('is_walkin');
            })
            ->orderBy('shopname')
            ->get(['id', 'name', 'shopname', 'shop_id']);

        $shopId = $authUser->shop_id ?? ActiveShop::current()?->id;
        $shopBanks = collect();
        if ($shopId) {
            $shopBanks = DB::table('bank_shop')
                ->where('bank_shop.shop_id', $shopId)
                ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
                ->select('bank_shop.id', 'banks.name')
                ->orderBy('banks.name')
                ->get();
        }

        $row = (int) $request->input('row', 15);
        if ($row < 1 || $row > 100) {
            $row = 15;
        }

        $listFilters = $this->resolveCustomerPaymentsListFilters($request);
        $paymentRowsQuery = $this->customerPaymentsFilteredQuery($request, $visibleShopIds, $listFilters);

        $paymentRowsQuery
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');
        $paymentRows = $paymentRowsQuery->paginate($row, ['*'], 'page');

        $customerIds = $paymentRows->pluck('account_ref_id')->unique()->filter()->values()->all();
        $customersById = $customerIds ? Customer::whereIn('id', $customerIds)->get()->keyBy('id') : collect();

        // Payment method from sibling cash/bank row (same shop, date, amount, description)
        $paymentMethodByKey = [];
        if ($paymentRows->isNotEmpty()) {
            $cashBankQuery = AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
                ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
                ->when($visibleShopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $visibleShopIds))
                ->whereIn('transaction_date', $paymentRows->pluck('transaction_date')->unique());
            foreach ($cashBankQuery->get() as $r) {
                $key = $r->shop_id . '|' . $r->transaction_date . '|' . (string) $r->amount . '|' . (string) ($r->description ?? '');
                $paymentMethodByKey[$key] = $r->account_type;
            }
        }

        $payments = $paymentRows->getCollection()->map(function ($row) use ($customersById, $paymentMethodByKey) {
            $key = $row->shop_id . '|' . $row->transaction_date . '|' . (string)$row->amount . '|' . (string)($row->description ?? '');
            return (object)[
                'id' => $row->id,
                'source_id' => $row->source_id,
                'receipt_no' => $row->receipt_no,
                'customer_id' => $row->account_ref_id,
                'customer_name' => $customersById->get($row->account_ref_id)?->shopname ?: $customersById->get($row->account_ref_id)?->name ?? '—',
                'transaction_date' => $row->transaction_date,
                'amount' => $row->amount,
                'description' => $row->description,
                'payment_method' => $paymentMethodByKey[$key] ?? '—',
            ];
        });
        $paymentRows->setCollection($payments);

        return view('customer-payments.create', [
            'customers' => $customers,
            'shopBanks' => $shopBanks,
            'payments' => $paymentRows,
            'dateRange' => [
                'date_filter' => $listFilters['date_filter'],
                'start_date' => $listFilters['start_date']?->format('Y-m-d'),
                'end_date' => $listFilters['end_date']?->format('Y-m-d'),
            ],
            'payment_method_filter' => $listFilters['payment_method_filter'],
        ]);
    }

    /**
     * Excel export for the filtered customer payments list (all matching rows, not paginated).
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        $listFilters = $this->resolveCustomerPaymentsListFilters($request);
        $rows = $this->customerPaymentsFilteredQuery($request, $visibleShopIds, $listFilters)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $customerIds = $rows->pluck('account_ref_id')->unique()->filter()->values()->all();
        $customersById = $customerIds ? Customer::whereIn('id', $customerIds)->get()->keyBy('id') : collect();
        $shopIds = $rows->pluck('shop_id')->unique()->filter()->values()->all();
        $shopsById = $shopIds ? Shop::whereIn('id', $shopIds)->get()->keyBy('id') : collect();

        $paymentMethodByKey = [];
        if ($rows->isNotEmpty()) {
            $cashBankQuery = AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
                ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
                ->when($visibleShopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $visibleShopIds))
                ->whereIn('transaction_date', $rows->pluck('transaction_date')->unique()->values()->all());
            foreach ($cashBankQuery->get() as $r) {
                $key = $r->shop_id . '|' . $r->transaction_date . '|' . (string) $r->amount . '|' . (string) ($r->description ?? '');
                $paymentMethodByKey[$key] = $r->account_type;
            }
        }

        $filename = 'customer-payments-' . now()->format('Y-m-d_His') . '.xlsx';
        $grandTotal = (float) $rows->sum('amount');

        return new StreamedResponse(function () use ($rows, $filename, $grandTotal, $customersById, $shopsById, $paymentMethodByKey) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([
                'Receipt No',
                'Shop',
                'Customer',
                'Date',
                'Amount',
                'Method',
                'Description',
                'Payment ID',
            ], null, 'A1');

            $rowIndex = 2;
            foreach ($rows as $paymentRow) {
                $cust = $customersById->get($paymentRow->account_ref_id);
                $customerDisplay = $cust?->shopname ?: $cust?->name ?: '—';
                $pk = $paymentRow->shop_id . '|' . $paymentRow->transaction_date . '|' . (string) $paymentRow->amount . '|' . (string) ($paymentRow->description ?? '');
                $acctType = $paymentMethodByKey[$pk] ?? null;
                $methodLabel = $acctType === AccountTransaction::ACCOUNT_TYPE_BANK
                    ? 'Bank'
                    : ($acctType === AccountTransaction::ACCOUNT_TYPE_CASH ? 'Cash' : '—');
                $sheet->fromArray([
                    $paymentRow->receipt_no ?? '—',
                    $shopsById->get($paymentRow->shop_id)?->name ?? '—',
                    $customerDisplay,
                    Carbon::parse($paymentRow->transaction_date)->format('Y-m-d'),
                    number_format((float) $paymentRow->amount, 2, '.', ''),
                    $methodLabel,
                    $paymentRow->description ?? '',
                    $paymentRow->id,
                ], null, 'A' . $rowIndex);
                $rowIndex++;
            }

            $sheet->setCellValue('D' . $rowIndex, 'Total');
            $sheet->setCellValue('E' . $rowIndex, number_format($grandTotal, 2, '.', ''));
            $sheet->getStyle('D' . $rowIndex . ':E' . $rowIndex)->getFont()->setBold(true);

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Store customer payment (double-entry): (1) cash/bank debit (money in), (2) customer credit (AR decrease).
     * Rule: Receiving payment → increase asset (debit cash/bank), decrease receivable (credit customer).
     * Also syncs customers.credit_amount via CustomerCreditService.
     */
    public function store(Request $request, CustomerCreditService $creditService)
    {
        $validated = $request->validate([
            'customer_id' => 'required|numeric|exists:customers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank',
            'shop_bank_id' => 'nullable|numeric|exists:bank_shop,id',
            'description' => 'nullable|string|max:500',
        ]);

        $authUser = auth()->user();
        $shopId = $authUser->shop_id ?? ActiveShop::current()?->id;
        if (!$shopId) {
            return Redirect::back()->withErrors(['customer_id' => 'Please select a shop context.'])->withInput();
        }

        $customer = Customer::findOrFail($validated['customer_id']);
        if ($authUser->shop_id && $customer->shop_id && $customer->shop_id != $authUser->shop_id) {
            return Redirect::back()->withErrors(['customer_id' => 'Customer does not belong to your shop.'])->withInput();
        }

        if ($validated['payment_method'] === 'bank' && empty($validated['shop_bank_id'])) {
            return Redirect::back()->withErrors(['shop_bank_id' => 'Please select a bank when payment method is Bank.'])->withInput();
        }

        // Parse from the posted string so values like 200 are not corrupted by IEEE-754 floats
        // before Eloquent's decimal:2 cast (BigDecimal HALF_UP), which could otherwise store 199.99.
        try {
            $amountMoney = BigDecimal::of(trim((string) $validated['amount']))
                ->toScale(2, RoundingMode::HALF_UP);
        } catch (BrickMathException $e) {
            return Redirect::back()->withErrors(['amount' => 'Enter a valid amount.'])->withInput();
        }

        if ($amountMoney->compareTo(BigDecimal::of('0.01')) < 0) {
            return Redirect::back()->withErrors(['amount' => 'Amount must be at least 0.01.'])->withInput();
        }

        $amountMoneyString = (string) $amountMoney;
        $amount = (float) $amountMoneyString;
        $transactionDate = Carbon::parse($validated['payment_date'])->toDateString();
        $isBank = $validated['payment_method'] === 'bank';
        $accountRefId = $isBank ? (int) $validated['shop_bank_id'] : null;

        $createdCustomerRowId = DB::transaction(function () use ($validated, $shopId, $amount, $amountMoneyString, $transactionDate, $isBank, $accountRefId, $customer, $creditService) {
            $receiptNo = $this->nextCustomerPaymentReceiptNo(Carbon::parse($transactionDate));

            // Row 1: Customer CREDIT — receivable decrease (customer owes less)
            $customerRow = AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
                'account_ref_id' => $validated['customer_id'],
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $amountMoneyString,
                'source_type' => AccountTransaction::SOURCE_CUSTOMER_PAYMENT,
                'source_id' => null,
                'receipt_no' => $receiptNo,
                'description' => $validated['description'] ?? 'Customer payment',
                'transaction_date' => $transactionDate,
            ]);
            $groupId = (int) $customerRow->id;
            $customerRow->source_id = $groupId;
            $customerRow->save();

            // Row 2: Cash/Bank DEBIT — money received (asset increase)
            AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH,
                'account_ref_id' => $accountRefId,
                'direction' => AccountTransaction::DIRECTION_DEBIT,
                'amount' => $amountMoneyString,
                'source_type' => AccountTransaction::SOURCE_CUSTOMER_PAYMENT,
                'source_id' => $groupId,
                'receipt_no' => $receiptNo,
                'description' => $validated['description'] ?? 'Customer payment',
                'transaction_date' => $transactionDate,
            ]);

            // Sync customers.credit_amount (decrease by payment amount)
            $creditService->applyPayment($customer, $amount);

            return $groupId;
        });

        return Redirect::route('customer-payments.create')->with([
            'success' => 'Customer payment recorded successfully.',
            'print_customer_payment_id' => $createdCustomerRowId,
        ]);
    }

    public function printA4(int $id, LedgerBalanceService $balanceService)
    {
        $payment = $this->resolvePaymentForPrint($id, $balanceService);

        return view('customer-payments.print-a4', $payment);
    }

    public function printReceipt(int $id, LedgerBalanceService $balanceService)
    {
        $payment = $this->resolvePaymentForPrint($id, $balanceService);

        return view('customer-payments.print-receipt', $payment);
    }

    /**
     * Return payment detail HTML for ledger modal (AJAX). Id is the account_transaction id (customer-side row).
     */
    public function paymentDetailContent($id)
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $customerRow = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->findOrFail($id);

        if ($visibleShopIds->isNotEmpty() && !$visibleShopIds->contains($customerRow->shop_id)) {
            abort(404);
        }

        $customer = Customer::find($customerRow->account_ref_id);
        $desc = $customerRow->description ?? '';
        $siblingQuery = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
            ->where('shop_id', $customerRow->shop_id)
            ->where('transaction_date', $customerRow->transaction_date)
            ->where('amount', $customerRow->amount);
        if ($desc === '') {
            $siblingQuery->where(function ($q) {
                $q->whereNull('description')->orWhere('description', '');
            });
        } else {
            $siblingQuery->where('description', $desc);
        }
        $sibling = $siblingQuery->first();

        $paymentMethod = '—';
        $bankName = null;
        if ($sibling) {
            $paymentMethod = $sibling->account_type === AccountTransaction::ACCOUNT_TYPE_BANK ? 'Bank' : 'Cash';
            if ($sibling->account_type === AccountTransaction::ACCOUNT_TYPE_BANK && $sibling->account_ref_id) {
                $bankName = DB::table('bank_shop')
                    ->where('bank_shop.id', $sibling->account_ref_id)
                    ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
                    ->value('banks.name');
            }
        }

        $html = view('customer-payments.partials.payment-detail-content', [
            'transaction' => $customerRow,
            'customer' => $customer,
            'payment_method' => $paymentMethod,
            'bank_name' => $bankName,
            'in_modal' => true,
        ])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * Soft delete a customer payment: soft delete the customer-side and cash/bank account_transactions,
     * then recalculate and sync the customer balance. All in a transaction.
     */
    public function destroy($id, LedgerBalanceService $balanceService)
    {
        $request = request();
        if (!$request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
        }

        try {
            DB::transaction(function () use ($id, $balanceService) {
                $customerRow = AccountTransaction::query()
                    ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
                    ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
                    ->findOrFail($id);

                $customerRow->delete(); // soft delete

                $desc = $customerRow->description ?? '';
                $siblingQuery = AccountTransaction::query()
                    ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
                    ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
                    ->where('shop_id', $customerRow->shop_id)
                    ->where('transaction_date', $customerRow->transaction_date)
                    ->where('amount', $customerRow->amount);
                if ($desc === '') {
                    $siblingQuery->where(function ($q) {
                        $q->whereNull('description')->orWhere('description', '');
                    });
                } else {
                    $siblingQuery->where('description', $desc);
                }
                $siblings = $siblingQuery->get();

                foreach ($siblings as $trans) {
                    $trans->delete(); // soft delete
                }

                $customerId = (int) $customerRow->account_ref_id;
                $balance = $balanceService->getCustomerBalance($customerId, $customerRow->shop_id);
                Customer::where('id', $customerId)->update(['credit_amount' => $balance]);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting payment.'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * @return array{date_filter: string, start_date: ?Carbon, end_date: ?Carbon, payment_method_filter: string}
     */
    protected function resolveCustomerPaymentsListFilters(Request $request): array
    {
        $dateFilter = $request->input('date_filter', 'all');
        $startDate = null;
        $endDate = null;

        if ($dateFilter !== 'all') {
            switch ($dateFilter) {
                case 'today':
                    $startDate = Carbon::today();
                    $endDate = Carbon::today();
                    break;
                case 'yesterday':
                    $startDate = Carbon::yesterday();
                    $endDate = Carbon::yesterday();
                    break;
                case 'this_week':
                    $startDate = Carbon::now()->startOfWeek();
                    $endDate = Carbon::now()->endOfWeek();
                    break;
                case 'last_week':
                    $startDate = Carbon::now()->subWeek()->startOfWeek();
                    $endDate = Carbon::now()->subWeek()->endOfWeek();
                    break;
                case 'this_month':
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate = Carbon::now()->endOfMonth();
                    break;
                case 'last_month':
                    $startDate = Carbon::now()->subMonth()->startOfMonth();
                    $endDate = Carbon::now()->subMonth()->endOfMonth();
                    break;
                case 'this_year':
                    $startDate = Carbon::now()->startOfYear();
                    $endDate = Carbon::now()->endOfYear();
                    break;
                case 'last_year':
                    $startDate = Carbon::now()->subYear()->startOfYear();
                    $endDate = Carbon::now()->subYear()->endOfYear();
                    break;
                case 'custom':
                    $startDateInput = $request->input('start_date');
                    $endDateInput = $request->input('end_date');
                    $startDate = $startDateInput ? Carbon::parse($startDateInput)->startOfDay() : Carbon::today()->startOfDay();
                    $endDate = $endDateInput ? Carbon::parse($endDateInput)->endOfDay() : Carbon::today()->endOfDay();
                    break;
                default:
                    $dateFilter = 'all';
                    break;
            }
        }

        return [
            'date_filter' => $dateFilter,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payment_method_filter' => $request->input('payment_method_filter', ''),
        ];
    }

    /**
     * Base query for the customer-payment list sharing the same filters as the create-page table / export.
     *
     * @param  \Illuminate\Support\Collection|array<int, int>  $visibleShopIds
     */
    protected function customerPaymentsFilteredQuery(Request $request, $visibleShopIds, array $filters): Builder
    {
        $query = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->when($visibleShopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $visibleShopIds));

        if ($request->filled('customer_id')) {
            $query->where('account_ref_id', (int) $request->input('customer_id'));
        }

        $dateFilter = $filters['date_filter'];
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        if ($dateFilter !== 'all' && $startDate && $endDate) {
            $query->whereBetween('transaction_date', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s'),
            ]);
        }

        $paymentMethodFilter = $filters['payment_method_filter'];
        if (in_array($paymentMethodFilter, ['cash', 'bank'], true)) {
            $siblingAccountType = $paymentMethodFilter === 'cash'
                ? AccountTransaction::ACCOUNT_TYPE_CASH
                : AccountTransaction::ACCOUNT_TYPE_BANK;
            $table = (new AccountTransaction())->getTable();
            $query->whereExists(function ($sub) use ($siblingAccountType, $table) {
                $sub->select(DB::raw(1))
                    ->from($table . ' as cp_pay_method_sibling')
                    ->whereColumn('cp_pay_method_sibling.source_id', $table . '.source_id')
                    ->whereColumn('cp_pay_method_sibling.shop_id', $table . '.shop_id')
                    ->whereNull('cp_pay_method_sibling.deleted_at')
                    ->where('cp_pay_method_sibling.source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
                    ->where('cp_pay_method_sibling.account_type', $siblingAccountType);
            });
        }

        return $query;
    }

    protected function resolvePaymentForPrint(int $id, LedgerBalanceService $balanceService): array
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $customerRow = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->findOrFail($id);

        if ($visibleShopIds->isNotEmpty() && !$visibleShopIds->contains($customerRow->shop_id)) {
            abort(404);
        }

        $groupId = (int) ($customerRow->source_id ?: $customerRow->id);
        if ((int) ($customerRow->source_id ?? 0) !== $groupId) {
            $customerRow->source_id = $groupId;
            $customerRow->save();
        }

        $sibling = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
            ->where('shop_id', $customerRow->shop_id)
            ->where('source_id', $groupId)
            ->orderBy('id')
            ->first();

        if (!$sibling) {
            $sibling = AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
                ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
                ->where('shop_id', $customerRow->shop_id)
                ->whereDate('transaction_date', $customerRow->transaction_date)
                ->where('amount', $customerRow->amount)
                ->orderBy('id')
                ->first();
        }

        $receiptNo = $customerRow->receipt_no;
        if (!$receiptNo) {
            $receiptNo = $this->nextCustomerPaymentReceiptNo(Carbon::parse($customerRow->transaction_date));
            $customerRow->receipt_no = $receiptNo;
            $customerRow->save();
            if ($sibling) {
                $sibling->receipt_no = $receiptNo;
                $sibling->source_id = $groupId;
                $sibling->save();
            }
        }

        $customer = Customer::find($customerRow->account_ref_id);
        $shop = Shop::find($customerRow->shop_id);
        $paymentMethod = $sibling?->account_type === AccountTransaction::ACCOUNT_TYPE_BANK ? 'Bank' : 'Cash';
        $bankName = null;
        if ($sibling && $sibling->account_type === AccountTransaction::ACCOUNT_TYPE_BANK && $sibling->account_ref_id) {
            $bankName = DB::table('bank_shop')
                ->where('bank_shop.id', $sibling->account_ref_id)
                ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
                ->value('banks.name');
        }

        $asOfAfter = (float) AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->where('account_ref_id', $customerRow->account_ref_id)
            ->where('shop_id', $customerRow->shop_id)
            ->where(function ($q) use ($customerRow) {
                $q->whereDate('transaction_date', '<', $customerRow->transaction_date)
                    ->orWhere(function ($inner) use ($customerRow) {
                        $inner->whereDate('transaction_date', $customerRow->transaction_date)
                            ->where('id', '<=', $customerRow->id);
                    });
            })
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) as balance")
            ->value('balance') ?? 0.0;

        $beforeBalance = $asOfAfter + (float) $customerRow->amount;
        $latestBalance = $balanceService->getCustomerBalance((int) $customerRow->account_ref_id, (int) $customerRow->shop_id);

        return [
            'transaction' => $customerRow,
            'customer' => $customer,
            'shop' => $shop,
            'payment_method' => $paymentMethod,
            'bank_name' => $bankName,
            'receipt_no' => $receiptNo,
            'before_balance' => $beforeBalance,
            'after_balance' => $asOfAfter,
            'latest_balance' => $latestBalance,
            'received_by' => auth()->user(),
        ];
    }

    protected function nextCustomerPaymentReceiptNo(Carbon $date): string
    {
        $year = $date->format('Y');
        $prefix = 'CPR-' . $year . '-';

        $latest = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->whereNotNull('receipt_no')
            ->where('receipt_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('receipt_no');

        $nextNumber = 1;
        if ($latest && preg_match('/^CPR-\d{4}-(\d+)$/', (string) $latest, $m)) {
            $nextNumber = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
