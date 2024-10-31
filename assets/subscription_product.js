jQuery( document ).ready( function() {
    jQuery( '.options_group.pricing' ).addClass( 'show_if_netdebit_subscription' );
    jQuery('select#product-type').trigger('change');
});