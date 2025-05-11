jQuery(document).ready(function($) {
    // Handle custom place order button click
    $(document).on('click', '#lapinopay-place-order', function(e) {
        e.preventDefault();
        console.log('clicked');
        // Trigger the original WooCommerce form submission
        $('form.woocommerce-checkout').submit();
    });

    // Add selected class to the default payment method
    $('.lapinopay-payment-method input[type="radio"]:checked')
        .closest('.lapinopay-payment-method')
        .addClass('selected');
}); 