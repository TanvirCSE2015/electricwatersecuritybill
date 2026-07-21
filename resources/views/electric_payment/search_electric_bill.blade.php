@php
    $totalPayable = 0;
    $previousDueAmount = $previousDue ? $previousDue->amount : 0;
@endphp

<div class="card-body">
    <h3 class="h4 text-center mb-4">বিল বিবরণ</h3>
    <h5 class="mb-3">দোকান তথ্য</h5>
    <p>
        <strong>দোকান নং:</strong> {{ $customer->shop_no }} <br>
        <strong>নাম:</strong> {{ $customer->name }} <br>
        <strong>এলাকা:</strong> {{ $customer->electricArea->name ?? '' }} <br>
        <strong>ব্লক:</strong> {{ $customer->block->bolck_name ?? '' }} <br>
        <strong>মিটার:</strong> {{ $customer->activeMeter->meter_number ?? '' }}
    </p>

    <!-- Hidden inputs for bill IDs -->
    @foreach ($customer->unpaidBills as $bill)
        <input type="hidden" name="bill_ids[]" class="bill-id-input" value="{{ $bill->id }}">
    @endforeach

    @if ($previousDue)
        <input type="hidden" id="previous_due_paid" class="previous-due-input" value="{{ $previousDueAmount }}">
    @endif

    <div class="table-responsive">
        <table class="table table-bordered mt-3">
            <thead class="table-light">
                <tr>
                    <th>মাস</th>
                    <th>বছর</th>
                    <th>পূর্বের রিডিং</th>
                    <th>বর্তমান রিডিং</th>
                    <th>ব্যবহৃত ইউনিট</th>
                    <th>সারচার্জ</th>
                    <th>মোট পরিশোধযোগ্য</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($customer->unpaidBills as $bill)
                    @php
                        $surcharge = $bill->calculated_surcharge ?? \App\Helpers\ElectricBillHelper::calculateSurcharge($bill);

                        if ($bill->surcharge > 0) {
                            $payable = $bill->total_amount;
                        } else {
                            $payable = $bill->total_amount + $surcharge;
                        }

                        $totalPayable += $payable;
                    @endphp
                    <tr>
                        <td>{{ \App\Helpers\ElectricBillHelper::en2bn($bill->bill_month_name) }}</td>
                        <td>{{ \App\Helpers\ElectricBillHelper::en2bn($bill->billing_year) }}</td>
                        <td>{{ \App\Helpers\ElectricBillHelper::en2bn($bill->reading->previous_reading ?? 0) }}</td>
                        <td>{{ \App\Helpers\ElectricBillHelper::en2bn($bill->reading->current_reading ?? 0) }}</td>
                        <td>{{ \App\Helpers\ElectricBillHelper::en2bn($bill->consumed_units) }}</td>
                        <td>{{ \App\Helpers\ElectricBillHelper::en2bn(number_format($surcharge, 2)) }}</td>
                        <td><strong>{{ \App\Helpers\ElectricBillHelper::en2bn(number_format($payable, 2)) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($previousDue)
    <div class="bg-light rounded p-2 my-2">
        <p class="text-danger mb-2 text-center">পূর্বের বকেয়া </p>
        <h2 class="amount-display text-danger fw-bold mb-2 text-center">
            ৳ <span>{{ \App\Helpers\ElectricBillHelper::en2bn(number_format($previousDueAmount, 2)) }}</span>
        </h2>
        <input type="hidden" id="previous_due_paid" class="form-control previous-due-input" value="{{ $previousDueAmount }}">
    </div>
    @endif

    <div class="bg-light rounded p-2 text-center my-2">
        <p class="text-muted mb-2">সার্চার্জ সহ মোট বকেয়া</p>
        <h2 class="amount-display text-primary fw-bold mb-0">
            ৳ <span>{{ \App\Helpers\ElectricBillHelper::en2bn(number_format($totalPayable + $previousDueAmount, 2)) }}</span>
        </h2>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-grid">
                <button type="button" id="payWaterBill" class="btn btn-success btn-lg" data-shop-no="{{ $customer->shop_no }}">
                    পেমেন্ট করুন
                </button>
            </div>
        </div>
    </div>
</div>
