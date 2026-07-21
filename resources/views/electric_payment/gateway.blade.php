<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap/css/bootstrap.min.css') }}">
    <style>
      :root{--primary:#0d2a63;--gold:#f2b90c;--orange:#f5871f;--bg:#eef1f5;}
      *{box-sizing:border-box;}
      body{background:var(--bg);font-family:'Segoe UI',Roboto,Arial,sans-serif;padding:24px 0;}
      .gw-card{max-width:420px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.15);}
      .gw-header{background:var(--gold);text-align:center;padding:60px 16px 16px;position:relative;}
      .gw-logo{width:64px;height:64px;border-radius:50%;background:var(--primary);position:absolute;top:10px;left:50%;transform:translateX(-50%);display:flex;align-items:center;justify-content:center;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.25);}
      .gw-header h1{margin:8px 0 0;color:var(--primary);font-size:20px;font-weight:700;}
      .gw-notice{background:var(--primary);color:#fff;font-size:12px;padding:12px 14px;display:flex;gap:8px;}
      .gw-notice .icon{width:18px;height:18px;border-radius:50%;background:var(--orange);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex:0 0 auto;}
      .gw-body{padding:18px;font-size:13px;}
      .gw-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f0f0f0;}
      .gw-row .label{color:#777;font-style:italic;}
      .gw-row .value{font-weight:600;}
      .gw-total{display:flex;justify-content:space-between;align-items:flex-end;margin-top:12px;}
      .gw-total .amount{font-size:28px;font-weight:700;color:var(--primary);}
      .gw-methods{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;padding:18px;}
      .gw-method{border:2px solid #e2e2e2;border-radius:10px;padding:10px 6px;text-align:center;cursor:pointer;transition:.15s;}
      .gw-method.selected{border-color:var(--gold);box-shadow:0 0 0 2px rgba(224,168,0,.3);}
      .gw-method .badge{width:44px;height:44px;margin:0 auto 6px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:11px;}
      .gw-method .name{font-size:12px;color:#333;font-weight:600;}
      .badge-r{background:#8e2f8e;} .badge-b{background:#e2136e;} .badge-n{background:#f7941d;} .badge-t{background:#00a651;}
      .gw-footer{padding:0 18px 18px;}
      .gw-submit{width:100%;padding:13px;background:var(--orange);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;}
      .gw-submit:hover{background:#d97812;}
      .gw-cancel{display:block;width:100%;margin-top:8px;padding:10px;background:#fff;color:#555;border:1px solid #ccc;border-radius:8px;text-align:center;text-decoration:none;font-size:13px;}
    </style>
</head>
<body>

<div class="container">
    <div class="gw-card">
        <div class="gw-header">
            <div class="gw-logo">
                <img src="{{ asset('images/sonali.png') }}" alt="" style="width:34px;">
            </div>
            <h1>Sonali Payment Gateway</h1>
        </div>

        <form id="gatewayForm" method="POST" action="{{ route('electric.bill.process') }}">
            @csrf
            <input type="hidden" name="shop_no" value="{{ $shopNo }}">
            <input type="hidden" name="payment_method" id="selectedMethod" value="bKash">
            <input type="hidden" name="previous_due_paid" value="{{ $previousDueAmount ?? 0 }}">
            @foreach($bills ?? [] as $bill)
                <input type="hidden" name="bill_ids[]" value="{{ $bill->id }}">
            @endforeach

            <div class="gw-notice">
                <span class="icon">i</span>
                <span>আপনি এই সেবার জন্য আপনার অ্যাকাউন্ট থেকে টাকা পেমেন্ট করছেন। পেমেন্ট সম্পূর্ণ হলে তা ফিরিয়ে পাওয়া যাবে না।</span>
            </div>

            <div class="gw-body">
                <div class="gw-row">
                    <span class="label">Customer</span>
                    <span class="value">{{ $customer->name }}</span>
                </div>
                <div class="gw-row">
                    <span class="label">Shop No</span>
                    <span class="value">{{ $customer->shop_no }}</span>
                </div>
                <div class="gw-row">
                    <span class="label">Verified Bills</span>
                    <span class="value">{{ $bills->count() }} মাসের বিল</span>
                </div>
                <div class="gw-total">
                    <div>
                        <span class="label" style="display:block;font-style:italic;">Total Payable</span>
                        <span class="amount">৳ {{ number_format($grandTotal, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="gw-methods">
                <div class="gw-method selected" data-value="bKash">
                    <div class="badge badge-b">bKash</div>
                    <div class="name">bKash</div>
                </div>
                <div class="gw-method" data-value="Nagad">
                    <div class="badge badge-n">নাগাদ</div>
                    <div class="name">Nagad</div>
                </div>
                <div class="gw-method" data-value="Rocket">
                    <div class="badge badge-r">Rocket</div>
                    <div class="name">Rocket</div>
                </div>
                <div class="gw-method" data-value="Tap">
                    <div class="badge badge-t">Tap</div>
                    <div class="name">Tap</div>
                </div>
            </div>

            <div class="gw-footer">
                <button type="submit" class="gw-submit" id="gwPayBtn">
                    Pay ৳{{ number_format($grandTotal, 2) }} with bKash
                </button>
                <a href="{{ route('electric.bill.index') }}" class="gw-cancel">← Cancel / Back</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('.gw-method').forEach(el => {
        el.addEventListener('click', () => {
            document.querySelectorAll('.gw-method').forEach(x => x.classList.remove('selected'));
            el.classList.add('selected');
            const method = el.dataset.value;
            document.getElementById('selectedMethod').value = method;
            document.getElementById('gwPayBtn').textContent = `Pay ৳{{ number_format($grandTotal, 2) }} with ${method}`;
        });
    });

    // Dummy processing: form submits normally to processGatewayPayment
    // When integrating real gateway:
    // 1. Change form action to real gateway URL
    // 2. On gateway callback, verify signature
    // 3. Call /electric-bill/process-payment to create invoices
    document.getElementById('gatewayForm').addEventListener('submit', function(e) {
        // For dummy mode: let the form submit normally
    });
</script>

</body>
</html>
