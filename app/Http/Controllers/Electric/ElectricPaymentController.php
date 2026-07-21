<?php

namespace App\Http\Controllers\Electric;

use App\Models\Customer;
use App\Models\ElectricBill;
use App\Models\ElectricInvoice;
use App\Models\PreviousDue;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Helpers\ElectricBillHelper;

class ElectricPaymentController extends Controller
{
    public function index(){
        return view('electric_payment.electric');
    }

    public function search(Request $request)
    {
        $request->validate([
            'shop_no' => 'required'
        ]);

        $customer = Customer::with([
                'electricArea',
                'block',
                'activeMeter',
                'unpaidBills.reading'
            ])
            ->where('shop_no', $request->shop_no)
            ->first();

        if (!$customer || $customer->unpaidBills->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'কোনো বকেয়া বিল পাওয়া যায়নি'
            ]);
        }

        // Calculate surcharges matching Filament DueElectricBillDetails logic
        $customer->unpaidBills->each(function ($bill) {
            $bill->calculated_surcharge = ElectricBillHelper::calculateSurcharge($bill);
        });

        // Get previous due
        $previousDue = PreviousDue::where('customer_id', $customer->id)
            ->where('is_paid', false)
            ->first();

        $html = view('electric_payment.search_electric_bill', compact('customer', 'previousDue'))->render();

        return response()->json([
            'status' => true,
            'html' => $html
        ]);
    }

    public function pay(Request $request)
    {
        $data = $request->validate([
            'shop_no' => 'required|string',
            'bill_ids' => 'required|array|min:1',
            'bill_ids.*' => 'integer|exists:electric_bills,id',
            'previous_due_paid' => 'nullable|numeric|min:0',
        ]);

        $customer = Customer::where('shop_no', $data['shop_no'])->firstOrFail();
        $userId = auth()->id() ?? 1;

        $bills = ElectricBill::whereIn('id', $data['bill_ids'])
            ->where('customer_id', $customer->id)
            ->where('is_paid', false)
            ->get();

        if ($bills->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'কোনো বকেয়া বিল নেই',
            ]);
        }

        $paidPreviousDue = (float) ($data['previous_due_paid'] ?? 0);

        DB::transaction(function () use ($bills, $customer, $userId, $paidPreviousDue) {
            $totalAmount = 0;
            $lastSurcharge = 0;

            foreach ($bills as $bill) {
                if ($bill->surcharge > 0) {
                    $totalAmount += $bill->total_amount;
                    $lastSurcharge = $bill->surcharge;
                } else {
                    $surcharge = ElectricBillHelper::calculateSurcharge($bill);
                    $lastSurcharge = $surcharge;
                    $totalAmount += $bill->total_amount + $surcharge;
                }
            }

            $invoice = ElectricInvoice::create([
                'customer_id' => $customer->id,
                'invoice_date' => now(),
                'invoice_month' => now()->month,
                'invoice_month_name' => now()->format('F'),
                'invoice_year' => now()->year,
                'from_month' => $bills->first()->bill_month_name . '-' . $bills->first()->billing_year,
                'to_month' => $bills->count() === 1 ? '' : $bills->last()->bill_month_name . '-' . $bills->last()->billing_year,
                'total_amount' => $totalAmount,
                'created_by' => $userId,
            ]);

            ElectricBill::whereIn('id', $bills->pluck('id'))->update([
                'surcharge' => $lastSurcharge,
                'is_paid' => true,
                'paid_by' => $userId,
                'payment_date' => now(),
                'payment_method' => 'Online',
                'electric_invoice_id' => $invoice->id,
            ]);

            // Previous due payment
            if ($paidPreviousDue > 0) {
                $previousDue = PreviousDue::where('customer_id', $customer->id)
                    ->where('is_paid', false)
                    ->first();

                if ($previousDue) {
                    $dueTotal = $previousDue->amount - $paidPreviousDue;

                    $prevInvoice = ElectricInvoice::create([
                        'customer_id' => $customer->id,
                        'invoice_date' => now(),
                        'invoice_month' => now()->month,
                        'invoice_month_name' => now()->format('F'),
                        'invoice_year' => now()->year,
                        'from_month' => 'পূর্বের বকেয়া',
                        'to_month' => '',
                        'total_amount' => $paidPreviousDue,
                        'due_type' => 'previous_due',
                        'created_by' => $userId,
                    ]);

                    $previousDue->is_paid = $dueTotal == 0 ? true : false;
                    $previousDue->amount = $dueTotal;
                    $previousDue->save();
                }
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Payment done.',
        ]);
    }

    public function showGateway(Request $request)
    {
        $shopNo = $request->query('shop_no');
        $customer = Customer::where('shop_no', $shopNo)->firstOrFail();
        $bills = $customer->unpaidBills()->get();

        $grandTotal = 0;
        foreach ($bills as $bill) {
            if ($bill->surcharge > 0) {
                $grandTotal += $bill->total_amount;
            } else {
                $surcharge = ElectricBillHelper::calculateSurcharge($bill);
                $grandTotal += $bill->total_amount + $surcharge;
            }
        }

        $previousDue = PreviousDue::where('customer_id', $customer->id)
            ->where('is_paid', false)
            ->first();

        $previousDueAmount = $previousDue ? $previousDue->amount : 0;
        $grandTotal += $previousDueAmount;

        return view('electric_payment.gateway', compact('customer', 'bills', 'grandTotal', 'shopNo', 'previousDue', 'previousDueAmount'));
    }

    public function processGatewayPayment(Request $request)
    {
        $data = $request->validate([
            'shop_no' => 'required|string',
            'bill_ids' => 'required|array|min:1',
            'bill_ids.*' => 'integer|exists:electric_bills,id',
            'previous_due_paid' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string',
        ]);

        $customer = Customer::where('shop_no', $data['shop_no'])->firstOrFail();
        $userId = auth()->id() ?? 1;

        $bills = ElectricBill::whereIn('id', $data['bill_ids'])
            ->where('customer_id', $customer->id)
            ->where('is_paid', false)
            ->get();

        if ($bills->isEmpty()) {
            return back()->with('error', 'কোনো বকেয়া বিল পাওয়া যায়নি।');
        }

        $paidPreviousDue = (float) ($data['previous_due_paid'] ?? 0);
        $paymentMethod = $data['payment_method'];

        $invoice = null;
        $prevInvoice = null;

        DB::transaction(function () use (
            $bills, $customer, $userId, $paidPreviousDue, $paymentMethod,
            &$invoice, &$prevInvoice
        ) {
            $totalAmount = 0;
            $lastSurcharge = 0;

            foreach ($bills as $bill) {
                if ($bill->surcharge > 0) {
                    $totalAmount += $bill->total_amount;
                    $lastSurcharge = $bill->surcharge;
                } else {
                    $surcharge = ElectricBillHelper::calculateSurcharge($bill);
                    $lastSurcharge = $surcharge;
                    $totalAmount += $bill->total_amount + $surcharge;
                }
            }

            $invoice = ElectricInvoice::create([
                'customer_id' => $customer->id,
                'invoice_date' => now(),
                'invoice_month' => now()->month,
                'invoice_month_name' => now()->format('F'),
                'invoice_year' => now()->year,
                'from_month' => $bills->first()->bill_month_name . '-' . $bills->first()->billing_year,
                'to_month' => $bills->count() === 1 ? '' : $bills->last()->bill_month_name . '-' . $bills->last()->billing_year,
                'total_amount' => $totalAmount,
                'created_by' => $userId,
            ]);

            ElectricBill::whereIn('id', $bills->pluck('id'))->update([
                'surcharge' => $lastSurcharge,
                'is_paid' => true,
                'paid_by' => $userId,
                'payment_date' => now(),
                'payment_method' => $paymentMethod,
                'electric_invoice_id' => $invoice->id,
            ]);

            if ($paidPreviousDue > 0) {
                $previousDue = PreviousDue::where('customer_id', $customer->id)
                    ->where('is_paid', false)
                    ->first();

                if ($previousDue) {
                    $dueTotal = $previousDue->amount - $paidPreviousDue;

                    $prevInvoice = ElectricInvoice::create([
                        'customer_id' => $customer->id,
                        'invoice_date' => now(),
                        'invoice_month' => now()->month,
                        'invoice_month_name' => now()->format('F'),
                        'invoice_year' => now()->year,
                        'from_month' => 'পূর্বের বকেয়া',
                        'to_month' => '',
                        'total_amount' => $paidPreviousDue,
                        'due_type' => 'previous_due',
                        'created_by' => $userId,
                    ]);

                    $previousDue->is_paid = $dueTotal == 0 ? true : false;
                    $previousDue->amount = $dueTotal;
                    $previousDue->save();
                }
            }
        });

        $receiptUrl = route('electric-receipt.print', [
            'id' => $invoice->id,
        ]);

        $receiptUrls = [
            'current' => $receiptUrl,
        ];

        if ($paidPreviousDue > 0) {
            $receiptUrls['previous_due'] = route('electric-receipt.print', [
                'id' => $prevInvoice->id,
            ]);
        }

        return redirect()->route('electric.bill.index')->with([
            'payment_success' => true,
            'receipt_urls' => $receiptUrls,
            'message' => 'পেমেন্ট সফলভাবে সম্পূর্ণ হয়েছে।',
        ]);
    }
}
