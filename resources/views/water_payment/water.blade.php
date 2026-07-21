<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="{{ asset('images/favicon.png') }}" type="image/png">

    <title>Water Bill Payment</title>

    <link rel="stylesheet" href="{{ asset('css/bootstrap/css/bootstrap.min.css') }}">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-brand img {
            height: 60px;   /* Adjust logo size */
        }
        @media (max-width: 420px) {
            nav h5 {
                font-size: 30px !important;
            }
            .navbar-brand img {
                height: 45px!important;   /* Adjust logo size */
            }
        }
    </style>
</head>   
<body class="bg-warning bg-opacity-10">
    <nav class="navbar navbar-light bg-success shadow-sm py-2">
        <div class="container position-relative d-flex align-items-center">
        
        <!-- Logo (left) -->
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="{{ asset('images/logo.png') }}" alt="Logo" style="height:60px;">
        </a>

        <!-- Centered Heading -->
        <h5 class="m-0 fw-bold text-white position-absolute top-50 start-50 translate-middle w-100 text-center"
            style="font-size:40px;">
            ঢাকা ক্যান্টনমেন্ট বোর্ড
        </h5>
    </div>
    </nav>
    <div class="container mt-2">
        <div class="container py-1">
        <a href="#" class="btn btn-secondary mb-3" onclick="goBack()">← ফিরে যান</a>
        <!-- Search Card -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="mt-3 text-center">
                <h1 class="h2 fw-bold mb-2"><i class=" fas fa-tint fa-1x text-primary"></i>পানি বিল পরিশোধ</h1>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h3 class="h4 mb-0">🔍 হোল্ডিং নাম্বার দিয়ে খুঁজুন</h3>
                </div>
                
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <input type="text" class="form-control form-control-lg" id="holding_number" placeholder="হোল্ডিং নাম্বার লিখুন" />
                        </div>
                        <div class="d-grid">
                            <button class="btn btn-primary btn-lg" id="search-bill">খুঁজুন</button>
                        </div>
                        <!-- Loading Spinner -->
                        <div id="loadingSpinner" class="text-center my-4 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">লোড হচ্ছে...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- No Bill Alert -->
        <div class="alert alert-danger text-center d-none" role="alert" id="noBill">
            <strong>⚠️ দুঃখিত!</strong> এই হোল্ডিং নাম্বারের জন্য কোন বিল পাওয়া যায়নি।
        </div>
        
        <!-- Bill Info Card -->
        <div class="card shadow-sm mt-4 border-0 d-none" id="billInfo">
            
        </div>

        <!-- Payment Success Invoices -->
        @if(session('payment_success'))
        <div class="alert alert-success mt-4" id="paymentSuccess">
            <h5 class="alert-heading">✅ {{ session('message') }}</h5>
            <hr>
            <p class="mb-2">তৈরি করা ইনভয়েসগুলো নিচে দেয়া হলো:</p>
            @php $receipts = session('receipt_urls', []); @endphp

            @if(isset($receipts['previous_due']))
            <a href="{{ $receipts['previous_due'] }}" target="_blank" class="btn btn-outline-primary w-100 mb-2">
                📄 পূর্বের বকেয়া রসিদ দেখুন / প্রিন্ট করুন
            </a>
            @endif

            @if(isset($receipts['current']))
            <a href="{{ $receipts['current'] }}" target="_blank" class="btn btn-primary w-100">
                📄 বর্তমান বিলের রসিদ দেখুন / প্রিন্ট করুন
            </a>
            @endif
        </div>
        @endif
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="{{ asset('js/bootstrap/js/bootstrap.min.js') }}"></script>
    <script>
        document.getElementById('search-bill').addEventListener('click', function () {
            let holdingInput = document.getElementById('holding_number');
            let holding_number = holdingInput.value.trim();

            if (!holding_number) {
                alert('দোকান আইডি লিখুন');
                holdingInput.focus();
                return;
            }

            holding_number = encodeURIComponent(holding_number);

            const spinner = document.getElementById('loadingSpinner');
            const billInfo = document.getElementById('billInfo');

            // Reset UI
            billInfo.innerHTML = '';
            billInfo.classList.add('d-none');

            // Show spinner
            spinner.classList.remove('d-none');

            fetch(`{{ route('water.bill.search') }}?holding_number=${holding_number}`)
                .then(res => res.json())
                .then(res => {
                    spinner.classList.add('d-none'); // ❌ hide spinner

                    if (!res.status) {
                        alert(res.message);
                        return;
                    }

                    // ✅ Inject HTML
                    billInfo.innerHTML = res.html;
                    billInfo.classList.remove('d-none');

                    // 🎯 Smooth scroll to bill info
                    billInfo.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                })
                .catch(err => {
                    spinner.classList.add('d-none');
                    console.error(err);
                    alert('সার্ভার সমস্যা হয়েছে');
                });
        });
    </script>
    <script>
    // Redirect to dummy payment gateway when pay button is clicked
    document.addEventListener('click', function(event) {
        const btn = event.target.closest('#payWaterBill');
        if (!btn) return;

        const holdingNumber   = btn.dataset.holdingNumber;
        const billIds         = [...document.querySelectorAll('.bill-id-input')].map(el => el.value);
        const securityIds     = [...document.querySelectorAll('.security-id-input')].map(el => el.value);
        const previousDuePaid = document.querySelector('.previous-due-input')?.value ?? '0';
        const sPreviousDuePaid = document.querySelector('.security-due-input')?.value ?? '0';

        const params = new URLSearchParams({
            holding_number: holdingNumber,
            previous_due_paid: previousDuePaid,
            s_previous_due_paid: sPreviousDuePaid,
        });

        billIds.forEach(id => params.append('bill_ids[]', id));
        securityIds.forEach(id => params.append('security_ids[]', id));

        window.location.href = `{{ route('water.bill.gateway') }}?${params.toString()}`;
    });
    </script>
</body>
</html>