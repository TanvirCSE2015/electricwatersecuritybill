@php
    $totalWaterBill = 0;
    $totalSecurityBill = 0;
    $grandTotal = 0;
@endphp

<div class="card-body">
    <h3 class="h4 text-center mb-4">বিল বিবরণ</h3>
    <h5 class="mb-3">কাস্টমার তথ্য</h5>
    <p>
        <strong>হোল্ডিং নাম্বার:</strong> {{ $customer->holding_number }} <br>
        <strong>নাম:</strong> {{ $customer->customer_name ?? $customer->name ?? '' }} <br>
        <strong>ফোন:</strong> {{ $customer->customer_phone ?? $customer->phone ?? '' }}
    </p>

    <div class="table-responsive">
        <table class="table table-bordered mt-3">
            <thead class="table-light">
                <tr>
                    <th>মাস</th>
                    <th>বছর</th>
                    <th>বেসিক বিল</th>
                    <th>নির্মাণাধীন বিল</th>
                    <th>সারচার্জ %</th>
                    <th>সারচার্জ</th>
                    <th>পানি বিল</th>
                    <th>সিকিউরিটি বিল</th>
                    <th>মোট পরিশোধযোগ্য</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bills as $bill)
                    @php
                        $payableWater = $bill->total_amount + $bill->calculated_surcharge;
                        $payableSecurity = $bill->security_amount ?? 0;
                        $totalPayable = $payableWater + $payableSecurity;
                        $totalWaterBill += $payableWater;
                        $totalSecurityBill += $payableSecurity;
                        $grandTotal += $totalPayable;
                    @endphp
                    <tr>
                        <td>{{ \App\Helpers\WaterBillHelper::en2bn(\Carbon\Carbon::create()->month($bill->water_bill_month)->translatedFormat('F')) }}</td>
                        <td>{{ \App\Helpers\WaterBillHelper::en2bn($bill->water_bill_year) }}</td>
                        <td>{{ \App\Helpers\WaterBillHelper::en2bn(number_format($bill->base_amount, 2)) }}</td>
                        <td>{{ \App\Helpers\WaterBillHelper::en2bn(number_format($bill->cons_amount ?? 0, 2)) }}</td>
                        <td>{{ \App\Helpers\WaterBillHelper::en2bn($bill->surcharge_percent) }}%</td>
                        <td>{{ \App\Helpers\WaterBillHelper::en2bn(number_format($bill->calculated_surcharge, 2)) }}</td>
                        <td>{{ \App\Helpers\WaterBillHelper::en2bn(number_format($payableWater, 2)) }}</td>
                        <td>{{ \App\Helpers\WaterBillHelper::en2bn(number_format($payableSecurity, 2)) }}</td>
                        <td><strong>{{ \App\Helpers\WaterBillHelper::en2bn(number_format($totalPayable, 2)) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Previous Due (from water_customers table) --}}
    <div class="bg-light rounded p-2 text-center my-2">
        <p class="text-danger mb-2">পানির পূর্বের বকেয়া </p>
        <h2 class="amount-display text-danger fw-bold mb-0">
            ৳ <span>{{ \App\Helpers\WaterBillHelper::en2bn(number_format($previousDue, 2)) }}</span>
        </h2>
    </div>

    {{-- Security Previous Due (from water_customers table) --}}
    <div class="bg-light rounded p-2 text-center my-2">
        <p class="text-warning mb-2">সিকিউরিটির পূর্বের বকেয়া </p>
        <h2 class="amount-display text-warning fw-bold mb-0">
            ৳ <span>{{ \App\Helpers\WaterBillHelper::en2bn(number_format($sPreviousDue, 2)) }}</span>
        </h2>
    </div>

    <div class="bg-light rounded p-2 text-center my-2">
        <p class="text-muted mb-2">সার্চার্জ সহ মোট বকেয়া</p>
        <h2 class="amount-display text-primary fw-bold mb-0">
            ৳ <span>{{ \App\Helpers\WaterBillHelper::en2bn(number_format($grandTotal + $previousDue + $sPreviousDue, 2)) }}</span>
        </h2>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-grid">
                <button type="button" id="payWaterBill" class="btn btn-success btn-lg" data-holding-number="{{ $customer->holding_number }}">
                    পেমেন্ট করুন
                </button>
            </div>
        </div>
    </div>
</div>