import { Controller } from '@hotwired/stimulus';

let iti = null;
let input = null;

export default class extends Controller
{
    connect()
    {
        let uri = window.location.pathname;
    }

    onClickProceedToCheckout(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let self = this;

        $.ajax({
            async: "true",
            url: "/retail/checkout/options",
            type: 'POST',
            dataType: 'json',
            data: {
                'basket-id': $(clickedElement).data('basket-id'),
                'require-auth': false,
                'retail': true,
            },
            beforeSend: function ()
            {
                self.isLoading(true);
            },
            complete: function(e)
            {
                if(e.status === 500){
                    //window.location.href = '/retail/error';
                }
            },
            success: function (response)
            {
                $('#modal_address').remove();
                $('#basket_action_row_1').remove();
                $('#saved_items, #modal_address').remove();
                $('#basket_items').empty().append(response.body).addClass('bg-white mx-0 mx-sm-3');
                $('#half_border_row').addClass('px-0 px-sm-2');
                $('#basket_header').empty().append(response.header);
                $('#shipping_address_id').val(response.default_address_id);
                $('#billing_address_id').val(response.default_billing_address_id);
                $('#address_modal_label').empty().append('Select an Existing Address');
                $('.modal-header').empty()
                    .append('<h5 class="modal-title" id="address_modal_label">Use an Existing Address</h5>')
                    .append('<span class="badge bg-success ms-3 toggle_address" role="button">Create A New Address</span>')
                    .append('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>');
                $('.modal-body-address-new').hide();
                $('.modal-body-address-existing').append(response.existing_shipping_addresses).show();
                self.isLoading(false);
                $('#modal_header_address').empty()
                    .append('<h5 class="modal-title" id="address_modal_label">Use an Existing Address</h5>')
                    .append('<span class="badge bg-success ms-3 toggle_address" role="button">Create A New Address</span>')
                    .append('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>');
                $('#btn_checkout').hide();
                $('#half_border_row').removeClass('px-sm-2');
                $('#basket_items').removeClass('mx-sm-3');
                window.scrollTo(0, 0);
            }
        });
    }

    onClickModalShippingAddress(e)
    {
        let clickedElement = e.currentTarget;
        let orderId = $(clickedElement).attr('data-order-id');

        $.ajax({
            async: "true",
            url: "/retail/checkout/get-address-modal/2",
            type: 'POST',
            dataType: 'json',
            data:{
                'retail': true,
                'order-id': orderId,
            },
            success: function (response) {
                $('#billing_address_modal').empty();
                $('#shipping_address_modal').empty().append(response.modal);
                $('#address_type').attr('readonly', true);
                $('#option_shipping').attr('selected', true);
                $('#modal_header_address').empty()
                    .append('<h5 class="modal-title" id="address_modal_label">Use an Existing Address</h5>')
                    .append('<span class="badge bg-success ms-3 toggle_address" role="button" data-action="click->retail-checkout#onClickNewAddress">Create A New Address</span>')
                    .append('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>');
                $('.modal-body-address-new').hide();
                $('.modal-body-address-existing').show();
                $('#address_type').val(2);
            }
        });
    }

    onClickModalBillingAddress(e)
    {
        let clickedElement = e.currentTarget;
        let orderId = $(clickedElement).attr('data-order-id');

        $.ajax({
            async: "true",
            url: "/retail/checkout/get-address-modal/1",
            type: 'POST',
            dataType: 'json',
            data:{
                'retail': true,
                'order-id': orderId,
            },
            success: function (response) {
                $('#shipping_address_modal').empty();
                $('#billing_address_modal').empty().append(response.modal);
                $('#modal_header_address').empty()
                    .append('<h5 class="modal-title" id="address_modal_label">Use an Existing Address</h5>')
                    .append('<span class="badge bg-success ms-3 toggle_address" role="button" data-action="click->retail-checkout#onClickNewAddress">Create A New Address</span>')
                    .append('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>');
                $('.modal-body-address-new').hide();
                $('.modal-body-address-existing').show();
                $('#address_type').val(1);
            }
        });
    }

    onClickNewAddress(e)
    {
        e.preventDefault();

        input = document.querySelector("#address_mobile");
        iti = window.intlTelInput(input, {
            preferredCountries: ['za','ae','qa', 'bh', 'om', 'sa'],
            autoPlaceholder: "polite",
            nationalMode: true,
            separateDialCode: true,
            utilsScript: "/js/utils.js",
        });

        if($('.modal-body-address-new:visible').is(':visible'))
        {
            $('#modal_header_address').empty()
                .append('<h5 class="modal-title" id="address_modal_label">Use an Existing Address</h5>')
                .append('<span class="badge bg-success ms-3 toggle_address" role="button" data-action="click->retail-checkout#onClickNewAddress">Create A New Address</span>')
                .append('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>');
            $('.existing-address').attr('disabled', false);
        }
        else
        {
            $('#modal_header_address').empty()
                .append('<h5 class="modal-title" id="address_modal_label">Create A New Address</h5>')
                .append('<span class="badge bg-success ms-3 toggle_address" role="button" data-action="click->retail-checkout#onClickNewAddress">Use an Existing Address</span>')
                .append('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>');
            $('.existing-address').attr('disabled', true);
        }

        $('.modal-body-address-new').toggle();
        $('.modal-body-address-existing').toggle();
    }

    onKeyUpMobileNo()
    {

        $('#address_telephone').val('');
        $('#address_iso_code').val('');
        $('#address_intl_code').val('');

        let handleChange = function()
        {
            let mobile = $('#address_telephone');
            let mobile_number = (iti.isValidNumber()) ? iti.getNumber() : false;
            let textNode = document.createTextNode(mobile_number);

            if(mobile_number != false)
            {
                let iso_code = iti.getSelectedCountryData().iso2;
                let intl_code = iti.getSelectedCountryData().dialCode;

                mobile.val(mobile_number.substring(1));
                $('#address_iso_code').val(iso_code);
                $('#address_intl_code').val(intl_code);

            }
        };

        // listen to "keyup", but also "change" to update when the user selects a country
        input.addEventListener('change', handleChange);
        input.addEventListener('keyup', handleChange);
    }

    onSubmitShippingAddress(e)
    {
        e.preventDefault();

        let data = new FormData($(this.element).find('#form_addresses_shipping_checkout')[0]);
        let isValid = true;
        let clinicName = $('#address_clinic_name').val()
        let telephone = $('#address_telephone').val();
        let addressLine1 = $('#address_line_1').val();
        let postalCode = $('#address_postal_code').val();
        let city = $('#address_city').val();
        let state = $('#address_state').val();
        let type = $('#address_type').val();
        let errorClinicName = $('#error_address_clinic_name');
        let errorTelephone = $('#error_address_telephone');
        let errorAddressLine1 = $('#error_address_line_1');
        let errorPostalCode = $('#error_address_postal_code');
        let errorCity = $('#error_address_city');
        let errorState = $('#error_address_state');
        let errorType = $('#error_address_type');

        errorClinicName.hide();
        errorTelephone.hide();
        errorAddressLine1.hide();
        errorPostalCode.hide();
        errorCity.hide();
        errorState.hide();
        errorType.hide();

        if(!$('.existing-address').is(':checked')) {
            if (clinicName == '' || clinicName == 'undefined') {
                errorClinicName.show();
                isValid = false;
            }

            if (telephone == '' || telephone == 'undefined') {
                errorTelephone.show();
                isValid = false;
            }

            if (addressLine1 == '' || addressLine1 == 'undefined') {
                errorAddressLine1.show();
                isValid = false;
            }

            if (city == '' || city == 'undefined') {
                errorCity.show();
                isValid = false;
            }

            if (postalCode == '' || postalCode == 'undefined') {
                errorPostalCode.show();
                isValid = false;
            }

            if (state == '' || state == 'undefined') {
                errorState.show();
                isValid = false;
            }

            if (type == '' || type == 'undefined') {
                errorType.show();
                isValid = false;
            }
        }

        if (isValid == true)
        {
            $.ajax({
                async: "true",
                url: "/retail/update-retail-address",
                type: 'POST',
                contentType: false,
                processData: false,
                cache: false,
                timeout: 600000,
                dataType: 'json',
                data: data,
                success: function (response)
                {
                    $('#checkout_shipping_address').empty().append(response.checkoutAddress);
                    $('#modal_shipping_address').modal('toggle');
                    $('.modal-backdrop').removeClass('modal-backdrop');
                    $('#modal_shipping_address').addClass('fade');
                    $('#basket_container').css({overflow: "auto"});
                    $('#address_clinic_name').val('');
                    $('#address_telephone').val('');
                    $('#address_line_1').val('');
                    $('#address_suite').val('');
                    $('#address_postal_code').val('');
                    $('#address_city').val('');
                    $('#address_state').val('');
                    $('#shipping_address_id').val(response.checkoutAddressId);
                }
            });
        }
    }

    onSubmitBillingAddress(e)
    {
        e.preventDefault();

        let data = new FormData($(this.element).find('#form_addresses_billing_checkout')[0]);
        let isValid = true;
        let clinicName = $('#address_clinic_name').val()
        let telephone = $('#address_telephone').val();
        let addressLine1 = $('#address_line_1').val();
        let postalCode = $('#address_postal_code').val();
        let city = $('#address_city').val();
        let state = $('#address_state').val();
        let type = $('#address_type').val();
        let errorClinicName = $('#error_address_clinic_name');
        let errorTelephone = $('#error_address_telephone');
        let errorAddressLine1 = $('#error_address_line_1');
        let errorPostalCode = $('#error_address_postal_code');
        let errorCity = $('#error_address_city');
        let errorState = $('#error_address_state');
        let errorType = $('#error_address_type');

        errorClinicName.hide();
        errorTelephone.hide();
        errorAddressLine1.hide();
        errorPostalCode.hide();
        errorCity.hide();
        errorState.hide();
        errorType.hide();

        if(!$('.existing-address').is(':checked'))
        {
            if (clinicName == '' || clinicName == 'undefined')
            {
                errorClinicName.show();
                isValid = false;
            }

            if (telephone == '' || telephone == 'undefined')
            {
                errorTelephone.show();
                isValid = false;
            }

            if (addressLine1 == '' || addressLine1 == 'undefined')
            {
                errorAddressLine1.show();
                isValid = false;
            }

            if (city == '' || city == 'undefined')
            {
                errorCity.show();
                isValid = false;
            }

            if (postalCode == '' || postalCode == 'undefined')
            {
                errorPostalCode.show();
                isValid = false;
            }

            if (state == '' || state == 'undefined')
            {
                errorState.show();
                isValid = false;
            }

            if (type == '' || type == 'undefined')
            {
                errorType.show();
                isValid = false;
            }
        }

        if (isValid == true)
        {
            $.ajax({
                async: "true",
                url: "/retail/update-retail-address",
                type: 'POST',
                contentType: false,
                processData: false,
                cache: false,
                timeout: 600000,
                dataType: 'json',
                data: data,
                success: function (response)
                {
                    $('#checkout_billing_address').empty().append(response.checkoutAddress);
                    $('#modal_billing_address').modal('toggle');
                    $('.modal-backdrop').removeClass('modal-backdrop');
                    $('#modal_billing_address').addClass('fade');
                    $('#basket_container').css({overflow: "auto"});
                    $('#address_clinic_name').val('');
                    $('#address_telephone').val('');
                    $('#address_line_1').val('');
                    $('#address_suite').val('');
                    $('#address_postal_code').val('');
                    $('#address_city').val('');
                    $('#address_state').val('');
                    $('#billing_address_id').val(response.checkoutAddressId);
                }
            });
        }
    }

    onClickShippingAddressMapBtn()
    {
        let visible = $('#address_map').is(":visible");
        let width = $(window).width();
        let modalHeight = $('#modal_shipping_address .modal-body').height();
        $('#address_map').toggle(700);

        if(visible == false)
        {
            let height = $('#modal_shipping_address .modal-body').height();
            $.session.set('modalHeight', height);

            if(width < 579)
            {
                $('#modal_shipping_address .modal-body').css('height', (modalHeight + 570));
            }
            else
            {
                $('#modal_shipping_address .modal-body').css('height',(modalHeight + 370));
            }
        }
        else
        {
            $('#modal_shipping_address .modal-body').css('height', $.session.get('modalHeight'));
        }
    }

    onClickBillingAddressMapBtn()
    {
        let visible = $('#address_map').is(":visible");
        let width = $(window).width();
        let modalHeight = $('#billing_address_modal .modal-body').height();
        $('#address_map').toggle(700);

        if(visible == false)
        {
            let height = $('#billing_address_modal .modal-body').height();
            $.session.set('modalHeight', height);

            if(width < 579)
            {
                $('#billing_address_modal .modal-body').css('height', (modalHeight + 570));
            }
            else
            {
                $('#billing_address_modal .modal-body').css('height',(modalHeight + 370));
            }

        } else {

            $('#billing_address_modal .modal-body').css('height', $.session.get('modalHeight'));
        }
    }

    onSubmitCheckoutOptions(e)
    {
        e.preventDefault();

        let confirmationEmail = $('#confirmation_email').val();
        let shippingAddress = $('#shipping_address_id').val();
        let billingAddress = $('#billing_address_id').val();
        let errorConfirmationEmail = $('#error_confirmation_email');
        let errorShippingAddress = $('#error_shipping_address');
        let errorBillingAddress = $('#error_billing_address');
        let self = this;
        let isValid = true;

        errorConfirmationEmail.hide();
        errorShippingAddress.hide();
        errorBillingAddress.hide();

        if(confirmationEmail == '' || confirmationEmail == 'undefined')
        {
            errorConfirmationEmail.show();
            isValid = false;
        }

        if(shippingAddress == '' || shippingAddress == 'undefined')
        {
            errorShippingAddress.show();
            isValid = false;
        }

        if(billingAddress == '' || billingAddress == 'undefined')
        {
            errorBillingAddress.show();
            isValid = false;
        }

        if(isValid)
        {
            let data = new FormData($(this.element).find('#form_checkout_options')[0]);

            $.ajax({
                async: "true",
                url: "/retail/checkout/save/options",
                type: 'POST',
                contentType: false,
                processData: false,
                cache: false,
                timeout: 600000,
                dataType: 'json',
                data: data,
                beforeSend: function ()
                {
                    self.isLoading(true);
                },
                success: function (response)
                {
                    $('#retail_container').empty().append(response);
                    window.scrollTo(0, 0);
                    self.isLoading(false);
                }
            });
        }
    }

    //Loading Overlay
    isLoading(status)
    {
        if(status)
        {
            $(this.element).closest('main').next('.overlay').addClass("show");
            $(this.element).closest('main').next('.overlay').addClass("show");
        }
        else
        {
            $(this.element).closest('main').next('.overlay').removeClass("show");
            $(this.element).closest('main').next('.overlay').removeClass("show");
        }
    }

    //Flash Message
    getFlash(flash, type = 'success')
    {
        $('#flash').addClass('alert-'+ type).addClass('alert').addClass('text-center');
        $('#flash').removeClass('users-flash').addClass('users-flash').empty().append(flash).removeClass('hidden');

        setTimeout(function() {
            $('#flash').removeClass('alert-success').removeClass('alert').removeClass('text-center');
            $('#flash').removeClass('users-flash').empty().addClass('hidden');
        }, 5000);
    }
}