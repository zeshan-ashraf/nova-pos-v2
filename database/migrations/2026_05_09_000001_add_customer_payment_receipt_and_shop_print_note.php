<?php

use App\Models\AccountTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_transactions') && !Schema::hasColumn('account_transactions', 'receipt_no')) {
            Schema::table('account_transactions', function (Blueprint $table) {
                $table->string('receipt_no', 32)->nullable()->after('source_id')->index();
            });
        }

        if (Schema::hasTable('shops') && !Schema::hasColumn('shops', 'print_note')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->text('print_note')->nullable()->after('invoice_policy');
            });
        }

        if (!Schema::hasTable('account_transactions')) {
            return;
        }

        $rows = DB::table('account_transactions')
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['id', 'shop_id', 'transaction_date', 'amount', 'description', 'source_id', 'receipt_no']);

        foreach ($rows as $row) {
            $groupId = (int) ($row->source_id ?: $row->id);
            if ((int) ($row->source_id ?? 0) !== $groupId) {
                DB::table('account_transactions')->where('id', $row->id)->update(['source_id' => $groupId]);
            }

            $receiptNo = $row->receipt_no ?: $this->nextReceiptNoForDate((string) $row->transaction_date);

            DB::table('account_transactions')->where('id', $row->id)->update([
                'source_id' => $groupId,
                'receipt_no' => $receiptNo,
            ]);

            $sibling = DB::table('account_transactions')
                ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
                ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
                ->where('shop_id', $row->shop_id)
                ->whereDate('transaction_date', $row->transaction_date)
                ->where('amount', $row->amount)
                ->when(
                    trim((string) ($row->description ?? '')) === '',
                    fn ($q) => $q->where(function ($inner) {
                        $inner->whereNull('description')->orWhere('description', '');
                    }),
                    fn ($q) => $q->where('description', $row->description)
                )
                ->where(function ($q) use ($groupId) {
                    $q->whereNull('source_id')->orWhere('source_id', $groupId);
                })
                ->orderBy('id')
                ->first(['id']);

            if ($sibling) {
                DB::table('account_transactions')
                    ->where('id', $sibling->id)
                    ->update([
                        'source_id' => $groupId,
                        'receipt_no' => $receiptNo,
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('account_transactions') && Schema::hasColumn('account_transactions', 'receipt_no')) {
            Schema::table('account_transactions', function (Blueprint $table) {
                $table->dropColumn('receipt_no');
            });
        }

        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'print_note')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropColumn('print_note');
            });
        }
    }

    private function nextReceiptNoForDate(string $date): string
    {
        $year = date('Y', strtotime($date));
        $prefix = 'CPR-' . $year . '-';

        $max = DB::table('account_transactions')
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->whereNotNull('receipt_no')
            ->where('receipt_no', 'like', $prefix . '%')
            ->pluck('receipt_no')
            ->map(function ($r) use ($prefix) {
                return (int) str_replace($prefix, '', (string) $r);
            })
            ->max();

        $next = ((int) $max) + 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
};

