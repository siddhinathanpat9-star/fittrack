<button id="pay-btn">Pay Gym Fees</button>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
var options = {
    "key": "rzp_test_SRQKNs34F8ti57",
    "amount": "50000",
    "currency": "INR",
    "name": "FitTrack Gym",
    "description": "Membership Payment",
    "handler": function (response){
        alert("Payment Successful " + response.razorpay_payment_id);
    }
};

var rzp = new Razorpay(options);

document.getElementById('pay-btn').onclick = function(e){
    rzp.open();
    e.preventDefault();
}
</script>