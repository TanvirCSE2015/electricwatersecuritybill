<?php

namespace App\Http\Controllers\Water;

use App\Models\SecurityBill;
use App\Models\WaterBill;
use App\Models\WaterCustomer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\SecurityInvoice;
use App\Models\WaterInvoice;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WaterPaymentController extends Controller
{
    public function index(){
        return view('water_payment.water');
    }
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'holding_number' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $customer = WaterCustomer::with([
                'waterBills',
                'securityBills',
            ])
            ->where('holding_number', $request->holding_number)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'হোল্ডিং নাম্বারটি খুঁজে পাওয়া যায়নি।'
            ]);
        }

        // Filter unpaid bills where due date has passed (matching Filament invoice logic)
        $bills = $customer->waterBills()
            ->where('is_paid', false)
            ->where('bill_due_date', '<', now())
            ->orderBy('water_bill_year')
            ->orderBy('water_bill_month')
            ->get();

        if ($bills->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'এই হোল্ডিং নাম্বারের কোনো বকেয়া বিল নেই।'
            ]);
        }

        // Calculate payable amounts per bill (matching WaterBillHelper::createInvoice logic)
        $bills->each(function ($bill) {
            $bill->calculated_surcharge = round($bill->total_amount * $bill->surcharge_percent / 100, 2);

            $securityBill = SecurityBill::where('water_customer_id', $bill->water_customer_id)
                ->where('s_bill_month', $bill->water_bill_month)
                ->where('s_bill_year', $bill->water_bill_year)
                ->first();

            $bill->security_amount = $securityBill ? $securityBill->total_amount : 0;
            $bill->security_id = $securityBill ? $securityBill->id : null;
        });

        $previousDue = $customer->previous_due ?? 0;
        $sPreviousDue = $customer->s_previous_due ?? 0;

        $html = view('water_payment.search_water_bill', compact('customer', 'bills', 'previousDue', 'sPreviousDue'))->render();

        return response()->json([
            'status' => true,
            'html' => $html
        ]);
    }

    // public function pay(Request $request)
    // {
    //     $data = $request->validate([
    //         'holding_number'         => 'required|string',
    //         'bill_ids'               => 'required|array|min:1',
    //         'bill_ids.*'             => 'integer|exists:water_bills,id',
    //         'security_ids'           => 'nullable|array',
    //         'security_ids.*'         => 'integer|exists:security_bills,id',
    //         'previous_due_paid'      => 'nullable|numeric|min:0',
    //         's_previous_due_paid'    => 'nullable|numeric|min:0',
    //     ]);

    //     $customer = WaterCustomer::where('holding_number', $data['holding_number'])->firstOrFail();
    //     $userId = 1;

    //     $bills = WaterBill::whereIn('id', $data['bill_ids'])
    //         ->where('water_customer_id', $customer->id)
    //         ->where('is_paid', false)
    //         ->where('bill_due_date', '<', now())
    //         ->get();

    //     if ($bills->isEmpty()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'সর্বপ্রথম বিল খুঁজুন বা কোনো বকেয়া বিল নেই।',
    //         ]);
    //     }

    //     $securityIds = collect($data['security_ids'] ?? [])->filter()->unique()->all();
    //     $totalSecurity = 0;

    //     if ($securityIds) {
    //         $totalSecurity = SecurityBill::whereIn('id', $securityIds)
    //             ->where('water_customer_id', $customer->id)
    //             ->sum('total_amount');
    //     }

    //     DB::transaction(function () use ($bills, $customer, $securityIds, $totalSecurity, $userId) {
    //         $totalWaterAmount = 0;
    //         foreach ($bills as $bill) {
    //             $surcharge = round($bill->total_amount * $bill->surcharge_percent / 100, 2);
    //             $payableAmount = $bill->total_amount + $surcharge;
    //             $totalWaterAmount += $payableAmount;

    //             $bill->update([
    //                 'surcharge_amount' => $surcharge,
    //                 'is_paid'          => true,
    //                 'paid_by'          => $userId,
    //                 'paid_at'          => now(),
    //                 'payment_method'   => 'Online',
    //                 'paid_amount'      => $payableAmount,
    //             ]);
    //         }

    //         $invoice = WaterInvoice::create([
    //             'water_customer_id'       => $customer->id,
    //             'w_invoice_date'          => now(),
    //             'w_invoice_month'         => now()->month,
    //             'w_invoice_month_name'    => now()->format('F'),
    //             'w_invoice_year'          => now()->year,
    //             'w_from_month'            => Carbon::create()->month($bills->first()->water_bill_month)->translatedFormat('F') . '-' . $bills->first()->water_bill_year,
    //             'w_to_month'              => Carbon::create()->month($bills->last()->water_bill_month)->translatedFormat('F') . '-' . $bills->last()->water_bill_year,
    //             'w_due_type'              => 'current',
    //             'w_total_amount'          => $totalWaterAmount,
    //             'w_created_by'            => $userId,
    //         ]);

    //         $bills->each->update(['water_invoice_id' => $invoice->id]);

    //         $sInvoice = null;
    //         if ($totalSecurity > 0) {
    //             $sInvoice = SecurityInvoice::create([
    //                 'water_customer_id'      => $customer->id,
    //                 's_invoice_date'         => now(),
    //                 's_invoice_month'        => now()->month,
    //                 's_invoice_month_name'   => now()->format('F'),
    //                 's_invoice_year'         => now()->year,
    //                 's_from_month'           => $invoice->w_from_month,
    //                 's_to_month'             => $invoice->w_to_month,
    //                 's_total_amount'         => $totalSecurity,
    //                 's_created_by'           => $userId,
    //             ]);

    //             SecurityBill::whereIn('id', $securityIds)
    //                 ->update([
    //                     'is_paid'             => true,
    //                     'security_invoice_id' => $sInvoice->id,
    //                     'paid_amount'         => $totalSecurity,
    //                 ]);
    //         }

    //         $paidPreviousDue   = (float) ($request->input('previous_due_paid') ?? 0);
    //         $sPaidPreviousDue  = (float) ($request->input('s_previous_due_paid') ?? 0);

    //         if ($paidPreviousDue > 0 || $sPaidPreviousDue > 0) {
    //             $customer->previous_due  = max(0, ($customer->previous_due ?? 0) - $paidPreviousDue);
    //             $customer->s_previous_due = max(0, ($customer->s_previous_due ?? 0) - $sPaidPreviousDue);
    //             $customer->save();
    //         }
    //     });

    //     $sId = $sInvoice->id ?? null;
    //     $receiptUrl = route('water-receipt.print', [
    //         'id'   => $invoice->id,
    //         's_id' => $sId,
    //         'type' => 'current',
    //     ]);

    //     return response()->json([
    //         'status'      => true,
    //         'message'     => 'পেমেন্ট সফলভাবে সম্পূর্ণ হয়েছে।',
    //         'receipt_url' => $receiptUrl,
    //     ]);
    // }
        public function pay(Request $request)
    {
        $data = $request->validate([
            'holding_number'         => 'required|string',
            'bill_ids'               => 'required|array|min:1',
            'bill_ids.*'             => 'integer|exists:water_bills,id',
            'security_ids'           => 'nullable|array',
            'security_ids.*'         => 'integer|exists:security_bills,id',
            'previous_due_paid'      => 'nullable|numeric|min:0',
            's_previous_due_paid'    => 'nullable|numeric|min:0',
        ]);

        $customer = WaterCustomer::where('holding_number', $data['holding_number'])->firstOrFail();
        $userId = auth()->id() ?? 1;

        // Get selected bills (must be unpaid AND overdue to match search logic)
        $bills = WaterBill::whereIn('id', $data['bill_ids'])
            ->where('water_customer_id', $customer->id)
            ->where('is_paid', false)
            ->where('bill_due_date', '<', now())
            ->orderBy('water_bill_year')
            ->orderBy('water_bill_month')
            ->get();

        if ($bills->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'সর্বপ্রথম বিল খুঁজুন বা কোনো বকেয়া বিল নেই।',
            ]);
        }

        // Filter security_ids to only those matching selected water bills
        $validSecurityIds = SecurityBill::where('water_customer_id', $customer->id)
            ->where('is_paid', false)
            ->whereIn(DB::raw('CONCAT(s_bill_year, "-", s_bill_month)'),
                $bills->map(fn ($b) => $b->water_bill_year . '-' . $b->water_bill_month)->all()
            )
            ->pluck('id')
            ->all();

        $securityIds = collect($data['security_ids'] ?? [])
            ->filter()
            ->unique()
            ->intersect($validSecurityIds)
            ->values()
            ->all();

        $totalSecurity = $securityIds
            ? (float) SecurityBill::whereIn('id', $securityIds)
                ->where('water_customer_id', $customer->id)
                ->sum('total_amount')
            : 0;

        $paidPreviousDue  = (float) ($data['previous_due_paid'] ?? 0);
        $sPaidPreviousDue = (float) ($data['s_previous_due_paid'] ?? 0);

        // Bind by reference to use after transaction
        $invoice         = null;
        $sInvoice        = null;
        $prevWaterInvoice = null;
        $prevSecInvoice   = null;

        DB::transaction(function () use (
            $bills, $customer, $securityIds, $totalSecurity,
            $userId, $paidPreviousDue, $sPaidPreviousDue,
            &$invoice, &$sInvoice, &$prevWaterInvoice, &$prevSecInvoice
        ) {
            $totalWaterAmount = 0;

            // Current bills processing
            foreach ($bills as $bill) {
                $surcharge     = round($bill->total_amount * $bill->surcharge_percent / 100, 2);
                $payableAmount = round($bill->total_amount + $surcharge, 2);
                $totalWaterAmount += $payableAmount;

                $bill->update([
                    'surcharge_amount' => $surcharge,
                    'is_paid'          => true,
                    'paid_by'          => $userId,
                    'paid_at'          => now(),
                    'payment_method'   => 'Online',
                    'paid_amount'      => $payableAmount,
                ]);
            }

            $fromMonth = Carbon::create()->month($bills->first()->water_bill_month)->translatedFormat('F')
                . '-' . $bills->first()->water_bill_year;
            $toMonth = $bills->count() === 1
                ? ''
                : Carbon::create()->month($bills->last()->water_bill_month)->translatedFormat('F')
                    . '-' . $bills->last()->water_bill_year;

            // Create Water Invoice for current bills
            $invoice = WaterInvoice::create([
                'water_customer_id'    => $customer->id,
                'w_invoice_date'       => now(),
                'w_invoice_month'      => now()->month,
                'w_invoice_month_name' => now()->format('F'),
                'w_invoice_year'       => now()->year,
                'w_from_month'         => $fromMonth,
                'w_to_month'           => $toMonth,
                'w_due_type'           => 'current',
                'w_total_amount'       => $totalWaterAmount,
                'w_created_by'         => $userId,
            ]);

            $bills->each->update(['water_invoice_id' => $invoice->id]);

            // Security Invoice for current bills
            if ($totalSecurity > 0 && $securityIds) {
                $sInvoice = SecurityInvoice::create([
                    'water_customer_id'    => $customer->id,
                    's_invoice_date'       => now(),
                    's_invoice_month'      => now()->month,
                    's_invoice_month_name' => now()->format('F'),
                    's_invoice_year'       => now()->year,
                    's_from_month'         => $invoice->w_from_month,
                    's_to_month'           => $invoice->w_to_month,
                    's_total_amount'       => $totalSecurity,
                    's_created_by'         => $userId,
                ]);

                SecurityBill::whereIn('id', $securityIds)->update([
                    'is_paid'             => true,
                    'security_invoice_id' => $sInvoice->id,
                    'paid_amount'           => $totalSecurity,
                ]);
            }

            // Create Previous Due Invoices (matching WaterBillHelper::previousDueInvoice logic)
            if ($paidPreviousDue > 0 || $sPaidPreviousDue > 0) {
                $customer->previous_due   = max(0, (float) ($customer->previous_due ?? 0) - $paidPreviousDue);
                $customer->s_previous_due = max(0, (float) ($customer->s_previous_due ?? 0) - $sPaidPreviousDue);
                $customer->save();

                if ($paidPreviousDue > 0) {
                    $prevWaterInvoice = WaterInvoice::create([
                        'water_customer_id'    => $customer->id,
                        'w_invoice_date'       => now(),
                        'w_invoice_month'      => now()->month,
                        'w_invoice_month_name' => now()->format('F'),
                        'w_invoice_year'       => now()->year,
                        'w_from_month'         => 'পূর্বের বকেয়া',
                        'w_to_month'           => '',
                        'w_due_type'           => 'previous',
                        'w_total_amount'       => $paidPreviousDue,
                        'w_created_by'         => $userId,
                    ]);
                }

                if ($sPaidPreviousDue > 0) {
                    $prevSecInvoice = SecurityInvoice::create([
                        'water_customer_id'    => $customer->id,
                        's_invoice_date'       => now(),
                        's_invoice_month'      => now()->month,
                        's_invoice_month_name' => now()->format('F'),
                        's_invoice_year'       => now()->year,
                        's_from_month'         => 'পূর্বের বকেয়া',
                        's_to_month'           => '',
                        'due_type'             => 'pre_due',
                        's_total_amount'       => $sPaidPreviousDue,
                        's_created_by'         => $userId,
                    ]);
                }
            }
        });

        // Build receipt URLs array
        $receiptUrls = [];

        if ($paidPreviousDue > 0 || $sPaidPreviousDue > 0) {
            $receiptUrls['previous_due'] = route('water-receipt.print', [
                'id'   => $prevWaterInvoice->id ?? null,
                's_id' => $prevSecInvoice->id ?? null,
                'type' => 'previous',
            ]);
        }

        $receiptUrls['current'] = route('water-receipt.print', [
            'id'   => $invoice->id,
            's_id' => $sInvoice->id ?? null,
            'type' => 'current',
        ]);

        return response()->json([
            'status'       => true,
            'message'      => 'পেমেন্ট সফলভাবে সম্পূর্ণ হয়েছে।',
            'receipt_urls' => $receiptUrls,
            'invoice'      => [
                'water_invoice_id' => $invoice->id,
                'security_invoice_id' => $sInvoice->id ?? null,
                'previous_water_invoice_id' => $prevWaterInvoice->id ?? null,
                'previous_security_invoice_id' => $prevSecInvoice->id ?? null,
            ],
        ]);
    }
        /**
     * Show payment gateway page with bill details
     */
    public function showGateway(Request $request)
    {
        $holdingNumber = $request->query('holding_number');
        $billIds = $request->query('bill_ids', []);
        $securityIds = $request->query('security_ids', []);
        $previousDuePaid = $request->query('previous_due_paid', 0);
        $sPreviousDuePaid = $request->query('s_previous_due_paid', 0);

        $customer = WaterCustomer::where('holding_number', $holdingNumber)->first();
        $bills = WaterBill::whereIn('id', $billIds)
            ->where('water_customer_id', $customer->id ?? 0)
            ->get();

        // Calculate totals
        $totalWaterAmount = 0;
        $totalSecurityAmount = 0;

        foreach ($bills as $bill) {
            $surcharge = round($bill->total_amount * $bill->surcharge_percent / 100, 2);
            $totalWaterAmount += $bill->total_amount + $surcharge;
        }

        if ($securityIds) {
            $totalSecurityAmount = SecurityBill::whereIn('id', $securityIds)
                ->where('water_customer_id', $customer->id ?? 0)
                ->sum('total_amount');
        }

        $grandTotal = $totalWaterAmount + $totalSecurityAmount + (float)$previousDuePaid + (float)$sPreviousDuePaid;

        return view('payment-gateway', compact(
            'customer',
            'bills',
            'holdingNumber',
            'billIds',
            'securityIds',
            'previousDuePaid',
            'sPreviousDuePaid',
            'grandTotal'
        ));
    }

    /**
     * Process payment from gateway (creates invoices)
     */
    public function processGatewayPayment(Request $request)
    {
        // This replicates the pay() logic but returns redirect to receipt
        $data = $request->validate([
            'holding_number'      => 'required|string',
            'bill_ids'            => 'required|array|min:1',
            'bill_ids.*'          => 'integer|exists:water_bills,id',
            'security_ids'        => 'nullable|array',
            'security_ids.*'      => 'integer|exists:security_bills,id',
            'previous_due_paid'   => 'nullable|numeric|min:0',
            's_previous_due_paid' => 'nullable|numeric|min:0',
            'payment_method'      => 'required|string',
        ]);

        $customer = WaterCustomer::where('holding_number', $data['holding_number'])->firstOrFail();
        $userId = auth()->id() ?? 1;

        $bills = WaterBill::whereIn('id', $data['bill_ids'])
            ->where('water_customer_id', $customer->id)
            ->where('is_paid', false)
            ->where('bill_due_date', '<', now())
            ->orderBy('water_bill_year')
            ->orderBy('water_bill_month')
            ->get();

        if ($bills->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'কোনো বকেয়া বিল পাওয়া যায়নি।',
            ]);
        }

        // Validate security IDs
        $validSecurityIds = SecurityBill::where('water_customer_id', $customer->id)
            ->where('is_paid', false)
            ->whereIn(DB::raw('CONCAT(s_bill_year, "-", s_bill_month)'),
                $bills->map(fn ($b) => $b->water_bill_year . '-' . $b->water_bill_month)->all()
            )
            ->pluck('id')
            ->all();

        $securityIds = collect($data['security_ids'] ?? [])
            ->filter()
            ->unique()
            ->intersect($validSecurityIds)
            ->values()
            ->all();

        $totalSecurity = $securityIds
            ? (float) SecurityBill::whereIn('id', $securityIds)
                ->where('water_customer_id', $customer->id)
                ->sum('total_amount')
            : 0;

        $paidPreviousDue  = (float) ($data['previous_due_paid'] ?? 0);
        $sPaidPreviousDue = (float) ($data['s_previous_due_paid'] ?? 0);
        $paymentMethod    = $data['payment_method'];

        $invoice         = null;
        $sInvoice        = null;
        $prevWaterInvoice = null;
        $prevSecInvoice   = null;

        DB::transaction(function () use (
            $bills, $customer, $securityIds, $totalSecurity,
            $userId, $paidPreviousDue, $sPaidPreviousDue, $paymentMethod,
            &$invoice, &$sInvoice, &$prevWaterInvoice, &$prevSecInvoice
        ) {
            $totalWaterAmount = 0;

            foreach ($bills as $bill) {
                $surcharge     = round($bill->total_amount * $bill->surcharge_percent / 100, 2);
                $payableAmount = round($bill->total_amount + $surcharge, 2);
                $totalWaterAmount += $payableAmount;

                $bill->update([
                    'surcharge_amount' => $surcharge,
                    'is_paid'          => true,
                    'paid_by'          => $userId,
                    'paid_at'          => now(),
                    'payment_method'   => $paymentMethod,
                    'paid_amount'      => $payableAmount,
                ]);
            }

            $fromMonth = Carbon::create()->month($bills->first()->water_bill_month)->translatedFormat('F')
                . '-' . $bills->first()->water_bill_year;
            $toMonth = $bills->count() === 1
                ? ''
                : Carbon::create()->month($bills->last()->water_bill_month)->translatedFormat('F')
                    . '-' . $bills->last()->water_bill_year;

            $invoice = WaterInvoice::create([
                'water_customer_id'    => $customer->id,
                'w_invoice_date'       => now(),
                'w_invoice_month'      => now()->month,
                'w_invoice_month_name' => now()->format('F'),
                'w_invoice_year'       => now()->year,
                'w_from_month'         => $fromMonth,
                'w_to_month'           => $toMonth,
                'w_due_type'           => 'current',
                'w_total_amount'       => $totalWaterAmount,
                'w_created_by'         => $userId,
            ]);

            $bills->each->update(['water_invoice_id' => $invoice->id]);

            if ($totalSecurity > 0 && $securityIds) {
                $sInvoice = SecurityInvoice::create([
                    'water_customer_id'    => $customer->id,
                    's_invoice_date'       => now(),
                    's_invoice_month'      => now()->month,
                    's_invoice_month_name' => now()->format('F'),
                    's_invoice_year'       => now()->year,
                    's_from_month'         => $invoice->w_from_month,
                    's_to_month'           => $invoice->w_to_month,
                    's_total_amount'       => $totalSecurity,
                    's_created_by'         => $userId,
                ]);

                SecurityBill::whereIn('id', $securityIds)->update([
                    'is_paid'             => true,
                    'security_invoice_id' => $sInvoice->id,
                    'paid_amount'           => $totalSecurity,
                ]);
            }

            if ($paidPreviousDue > 0 || $sPaidPreviousDue > 0) {
                $customer->previous_due   = max(0, (float) ($customer->previous_due ?? 0) - $paidPreviousDue);
                $customer->s_previous_due = max(0, (float) ($customer->s_previous_due ?? 0) - $sPaidPreviousDue);
                $customer->save();

                if ($paidPreviousDue > 0) {
                    $prevWaterInvoice = WaterInvoice::create([
                        'water_customer_id'    => $customer->id,
                        'w_invoice_date'       => now(),
                        'w_invoice_month'      => now()->month,
                        'w_invoice_month_name' => now()->format('F'),
                        'w_invoice_year'       => now()->year,
                        'w_from_month'         => 'পূর্বের বকেয়া',
                        'w_to_month'           => '',
                        'w_due_type'           => 'previous',
                        'w_total_amount'       => $paidPreviousDue,
                        'w_created_by'         => $userId,
                    ]);
                }

                if ($sPaidPreviousDue > 0) {
                    $prevSecInvoice = SecurityInvoice::create([
                        'water_customer_id'    => $customer->id,
                        's_invoice_date'       => now(),
                        's_invoice_month'      => now()->month,
                        's_invoice_month_name' => now()->format('F'),
                        's_invoice_year'       => now()->year,
                        's_from_month'         => 'পূর্বের বকেয়া',
                        's_to_month'           => '',
                        'due_type'             => 'pre_due',
                        's_total_amount'       => $sPaidPreviousDue,
                        's_created_by'         => $userId,
                    ]);
                }
            }
        });

        // Build receipt URLs for redirect
        $receiptUrls = [];

        if ($paidPreviousDue > 0 || $sPaidPreviousDue > 0) {
            $receiptUrls['previous_due'] = route('water-receipt.print', [
                'id'   => $prevWaterInvoice->id ?? null,
                's_id' => $prevSecInvoice->id ?? null,
                'type' => 'previous',
            ]);
        }

        $receiptUrls['current'] = route('water-receipt.print', [
            'id'   => $invoice->id,
            's_id' => $sInvoice->id ?? null,
            'type' => 'current',
        ]);

        return redirect()->route('water.bill.index')->with([
            'payment_success' => true,
            'receipt_urls'    => $receiptUrls,
            'message'         => 'পেমেন্ট সফলভাবে সম্পূর্ণ হয়েছে।',
        ]);
    }
}