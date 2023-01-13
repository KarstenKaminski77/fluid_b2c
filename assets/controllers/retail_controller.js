import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    connect()
    {
        // Handle page reload
        let uri = window.location.pathname;
        let isPersonalInformation = uri.match('/retail/personal-information');
        let isAddresses = uri.match('/retail/addresses/[0-9]+');
        let isAbout = uri.match('/retail/clinic/about');
        let isOperatingHours = uri.match('/retail/clinic/operating-hours');
        let isRefundPolicy = uri.match('/retail/clinic/refund-policy');
        let isSalesTaxPolicy = uri.match('/retail/clinic/sales-tax-policy');
        let isShippingPolicy = uri.match('/retail/clinic/shipping-policy');

        if(isPersonalInformation != null)
        {
            this.getPersonalInformation();
        }

        if(isAddresses != null)
        {
            this.getAddresses();
        }

        if(isAbout != null)
        {
            this.getCopy('getAbout', 'About');
        }
        if(isOperatingHours != null)
        {
            this.getCopy('getOperatingHours', 'Operating Hours');
        }

        if(isSalesTaxPolicy != null)
        {
            this.getCopy('getSalesTaxPolicy', 'Sales Tax Policy');
        }

        if(isRefundPolicy != null)
        {
            this.getCopy('getRefundPolicy', 'Refund Policy');
        }

        if(isShippingPolicy != null)
        {
            this.getCopy('getShippingPolicy', 'Shipping Policy');
        }

        $('body').addClass('d-flex flex-column h-100');

        // Basket ID
        $('#btn_basket').attr('data-basket-id', $(this.element).attr('data-basket-id'));

        // Notications
        $('#notifications_panel').hide();
    }

    // Personal Information
    personalInformation(e)
    {
        e.preventDefault();
        this.getPersonalInformation();
    }

    getPersonalInformation()
    {
        let self = this;

        $.ajax({
            url: "/retail/get-personal-information",
            type: 'POST',
            dataType: 'json',
            beforeSend: function ()
            {
                self.isLoading(true);
            },
            success: function (response)
            {
                self.isLoading(false);
                $('#retail_container').empty().append(response);
                window.history.pushState(null, "Fluid", '/retail/personal-information');

                // International Numbers
                let input = document.querySelector('#telephone');
                let isoCode = $('#iso_code').val();

                let iti = window.intlTelInput(input, {
                    initialCountry: isoCode,
                    preferredCountries: ['ae','qa', 'bh', 'om', 'sa'],
                    autoPlaceholder: "polite",
                    nationalMode: true,
                    separateDialCode: true,
                    utilsScript: "/js/utils.js",
                });

                let handleChange = function()
                {
                    let mobile = $('#mobile');
                    let mobile_number = (iti.isValidNumber()) ? iti.getNumber() : false;
                    let textNode = document.createTextNode(mobile_number);

                    if(mobile_number != false)
                    {
                        let isoCode = iti.getSelectedCountryData().iso2;
                        let intlCode = iti.getSelectedCountryData().dialCode;

                        mobile.val(mobile_number.substring(1));
                        $('#iso_code').val(isoCode);
                        $('#intl_code').val(intlCode);
                    }
                };

                // listen to "keyup", but also "change" to update when the user selects a country
                input.addEventListener('change', handleChange);
                input.addEventListener('keyup', handleChange);
            }
        });
    }

    onPersonalInfoSubmit(e)
    {
        e.preventDefault();

        let isValid = true;
        let firstName = $('#first_name').val();
        let lastName = $('#last_name').val();
        let email = $('#email').val();
        let telephone = $('#mobile_no').val();
        let errorFirstName = $('#error_first_name');
        let errorLastName = $('#error_last_name');
        let errorEmail = $('#error_email');
        let errorTelephone = $('#error_telephone');

        errorFirstName.hide();
        errorLastName.hide();
        errorEmail.hide();
        errorTelephone.hide();

        if(firstName == '' || firstName == 'undefined')
        {
            errorFirstName.show();
            isValid = false;
        }

        if(lastName == '' || lastName == 'undefined')
        {
            errorLastName.show();
            isValid = false;
        }

        if(email == '' || email == 'undefined')
        {
            errorEmail.empty().append('Required Field').show();
            isValid = false;
        }

        if(telephone == '' || telephone == 'undefined')
        {
            errorTelephone.show();
            isValid = false;
        }

        if(isValid == true)
        {
            let data = new FormData($(this.element).find('#retail_form')[0]);
            let self = this;

            $.ajax({
                url: "/retail/user/update",
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
                complete: function (e)
                {
                    if (e.status === 500)
                    {
                        window.location.href = '/retail/error';
                    }
                },
                success: function (response)
                {
                    self.getFlash(response.flash, response.type);
                    self.isLoading(false);
                }
            });
        }
    }

    // Addresses
    addressesList(e)
    {
        e.preventDefault();
        this.getAddresses(1);
    }
    addressNew(e)
    {
        e.preventDefault();
        this.getAddresses(1, true);
        $('#address_id').val(0);
        $('#address_clinic_name').val('');
        $('#address_mobile').val('');
        $('#address_line_1').val('');
        $('#address_modal_label').empty().append('Create An Address');
    }

    btnMap()
    {

        let gmapController = (function () {
            return initialize();
        })();

        let visible = $('#address_map').is(":visible");
        let width = $(window).width();
        let modalHeight = $('#modal_address .modal-body').height();
        $('#address_map').toggle(700);

        if(visible == false){

            let height = $('#modal_address .modal-body').height();
            $.session.set('modalHeight', height);

            if(width < 579) {

                $('#modal_address .modal-body').css('height', (modalHeight + 370));

            } else {

                $('#modal_address .modal-body').css('height', (modalHeight + 370));
            }

        } else {

            $('#modal_address .modal-body').css('height', $.session.get('modalHeight'));
        }
    }

    onAddressSubmit(e)
    {
        e.preventDefault();

        let isValid = true;
        let clinicName = $('#address_retail_name').val()
        let telephone = $('#address_telephone').val();
        let addressLine1 = $('#address_line_1').val();
        let type = $('#address_type').val();
        let errorClinicName = $('#error_address_retail_name');
        let errorTelephone = $('#error_address_telephone');
        let error_AddressLine1 = $('#error_address_line_1');
        let errorType = $('#error_address_type');

        errorClinicName.hide();
        errorTelephone.hide();
        error_AddressLine1.hide();
        errorType.hide();

        if(clinicName == '' || clinicName == 'undefined'){

            errorClinicName.show();
            isValid = false;
        }

        if(telephone == '' || telephone == 'undefined'){

            errorTelephone.show();
            isValid = false;
        }

        if(addressLine1 == '' || addressLine1 == 'undefined'){

            error_AddressLine1.show();
            isValid = false;
        }

        if(type == '' || type == 'undefined'){

            error_type.show();
            isValid = false;
        }

        if(isValid == true){

            $("<input />").attr("type", "hidden").attr("name", "page_id").attr("value", $('#page_no').val()).appendTo("#form_addresses");

            let data = new FormData($(this.element).find('#form_addresses')[0]);
            let self = this;

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
                beforeSend: function (){
                    self.isLoading(true);
                },
                success: function (response) {
                    self.getFlash(response.flash);

                    $('#modal_address').modal('toggle');
                    $('#retail_container').empty().append(response.addresses);
                    $('#paginator').empty().append(response.pagination).show();
                    self.isLoading(false);
                    $('#address_retail_name').val('');
                    $('#address_telephone').val('');
                    $('#address_line_1').val('');
                    $('#address_suite').val('');
                    $('#address_postal_code').val('');
                    $('#address_city').val('');
                    $('#address_state').val('');
                }
            });
        }
    }

    getAddressEditModal(e)
    {
        let clickedElement = e.currentTarget;
        let addressId = $(clickedElement).attr('data-address-id');

        $.ajax({
            async: "true",
            url: "/get-address",
            type: 'POST',
            timeout: 600000,
            data: {
                id: addressId
            },
            success: function (response) {
                $('#address_retail_name').val(response.clinic_name);
                $('#address_telephone').val(response.telephone);
                $('#address_mobile').val(response.telephone);
                $('#address_iso_code').val(response.iso_code);
                $('#address_intl_code').val(response.intl_code);
                $('#address_line_1').val(response.address);
                $('option').attr('selected', false);

                if(response.type == 1){

                    $('#option_billing').attr('selected', true);
                }

                if(response.type == 2){

                    $('#option_shipping').attr('selected', true);
                }

                let input = document.querySelector("#address_mobile");

                let iti = window.intlTelInput(input, {
                    preferredCountries: ['ae','qa', 'bh', 'om', 'sa'],
                    initialCountry: response.iso_code,
                    autoPlaceholder: "polite",
                    nationalMode: true,
                    separateDialCode: true,
                    utilsScript: "/js/utils.js",
                });
            }
        });

        $('#address_id').val(addressId);
        $('#address_modal_label').empty().append('Update An Address');
    }

    deleteAddress(e)
    {
        let clickedElement = e.currentTarget;
        let addressId = $(clickedElement).attr('data-address-id');
        let self = this;

        $.ajax(
            {
            async: "true",
            url: "/clinics/address/delete",
            type: 'POST',
            timeout: 600000,
            data: {
                id: addressId,
                page_id: 1
            },
            beforeSend: function () {
                self.isLoading(true);
            },
            success: function (response)
            {
                self.getFlash(response.flash);
                $('#modal_address_delete').modal('toggle');
                $('#retail_container').empty().append(response.addresses);
                $('#paginator').empty().append(response.pagination).show();

                self.isLoading(false);
            }
        });
    }

    getAddressDeleteModal(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        $('#delete_address').attr('data-address-id', $(clickedElement).attr('data-address-id'));
    }

    defaultShippingAddress(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let addressId = $(clickedElement).attr('data-address-id');
        let pageId = $('#page_no').val();
        let self = this;

        $.ajax({
            async: "true",
            url: "/retail/address/default",
            type: 'POST',
            dataType: 'json',
            data: {
                id: addressId,
                'page-id': pageId
            },
            beforeSend: function ()
            {
                self.isLoading(true);
            },
            success: function (response)
            {
                self.getFlash(response.flash);
                $('#retail_container').empty().append(response.addresses);
                $('#paginator').empty().append(response.pagination).show();
                self.isLoading(false);
            }
        });
    }

    defaultBillingAddress(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let addressId = $(clickedElement).data('billing-address-id');
        let pageId = $('#page_no').val();
        let self = this;

        $.ajax({
            async: "true",
            url: "/retail/address/default-billing",
            type: 'POST',
            dataType: 'json',
            data: {
                id: addressId,
                'page-id': pageId,
            },
            beforeSend: function ()
            {
                self.isLoading(true);
            },
            success: function (response)
            {
                self.getFlash(response.flash);
                $('#retail_container').empty().append(response.addresses);
                $('#paginator').empty().append(response.pagination).show();
                self.isLoading(false);
            }
        });
    }

    onLinkClick(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let method = $(clickedElement).attr('data-method');
        let name = $(clickedElement).attr('data-name');

        this.getCopy(method, name);
    }

    getCopy(method, name) {

        let self = this;

        $.ajax({
            async: "true",
            url: '/retail/get/clinic-copy',
            type: 'POST',
            dataType: 'json',
            data: {
                'method': method,
                'name': name,
            },
            beforeSend: function ()
            {
                self.isLoading(true)
            },
            complete: function(e)
            {
                if(e.status === 500)
                {
                    // window.location.href = '{{ path('retail_error_500') }}';
                }
            },
            success: function (response)
            {
                $('#retail_container').empty().append(response.html).show();
                window.scrollTo(0,0);
                self.isLoading(false);
                window.history.pushState(null, "Fluid", '/retail/clinic/'+ response.uri);
            }
        });
    }

    getAddresses(pageId, isNew = false)
    {
        let self = this;

        $.ajax({
            async: "true",
            url: '/retail/get-retail-addresses',
            type: 'POST',
            dataType: 'json',
            data: {
                'page-id': pageId,
            },
            beforeSend: function ()
            {
                self.isLoading(true)
            },
            complete: function(e)
            {
                if(e.status === 500)
                {
                    window.location.href = '/retail/error';
                }
            },
            success: function (response)
            {
                $('#retail_container').empty().append(response.html).show();
                window.scrollTo(0,0);
                self.isLoading(false);
                window.history.pushState(null, "Fluid", '/retail/addresses/1');

                if(isNew)
                {
                    let input = document.querySelector("#address_mobile");
                    let isoCode = $('#address_iso_code').val();

                    let iti = window.intlTelInput(input, {
                        initialCountry: isoCode,
                        preferredCountries: ['ae','qa', 'bh', 'om', 'sa'],
                        autoPlaceholder: "polite",
                        nationalMode: true,
                        separateDialCode: true,
                        utilsScript: "/js/utils.js",
                    });

                    let handleChange = function() {
                        let mobile = $('#address_telephone');
                        let mobileNumber = (iti.isValidNumber()) ? iti.getNumber() : false;
                        let textNode = document.createTextNode(mobileNumber);

                        if(mobileNumber != false){

                            let isoCode = iti.getSelectedCountryData().iso2;
                            let intlCode = iti.getSelectedCountryData().dialCode;

                            mobile.val(mobileNumber.substring(1));
                            $('#address_iso_code').val(isoCode);
                            $('#address_intl_code').val(intlCode);

                        }
                    };

                    // listen to "keyup", but also "change" to update when the user selects a country
                    input.addEventListener('change', handleChange);
                    input.addEventListener('keyup', handleChange);

                    $('#modal_address').modal('toggle');
                }
            }
        });
    }

    // Connect to clinic
    onClickBtnConnect(e)
    {
        e.preventDefault();

        let clinicId = $(this).attr('data-clinic-id');
        let clinicLogo = $(this).attr('data-clinic-logo')
        let clinicName = $(this).attr('data-clinic-name')

        $('#btn_request_connection').attr('data-clinic-id', clinicId);
        $('#logo_img').attr('src', clinicLogo);
        $('#modal_clinic_name').empty().append(clinicName);
        $('#modal_connect').modal('toggle');
    }

    onClickRequestConnection(e)
    {
        let clickedElement = e.currentTarget;
        let clinicId = $(clickedElement).attr('data-clinic-id');
        let retailUserId = $(clickedElement).attr('data-retail-user-id');
        let self = this;

        if(clinicId != '' || clinicId != 'undefined' || retailUserId != '' || retailUserId != 'undefined')
        {
            $.ajax({
                async: "true",
                url: '/retail/request-connection',
                type: 'POST',
                dataType: 'json',
                data: {
                    'clinic-id': clinicId,
                    'retail-user-id': retailUserId,
                },
                beforeSend: function ()
                {
                    self.isLoading(true)
                },
                complete: function(e)
                {
                    if(e.status === 500)
                    {
                        window.location.href = '/retail/error';
                    }
                },
                success: function (response)
                {
                    $('#modal_connect').modal('toggle');
                    $('.btn-retail-connect').addClass('bg-disabled').attr('disabled', true);
                    self.isLoading(false);
                    self.getFlash(response.flash, response.type);
                }
            });
        }
        else
        {
            alert('An error occurred');
        }
    }

    // Toggle Nav
    onClickToggleNav()
    {
        $('#main_nav').slideToggle(700);
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

    onClickFlash()
    {
        $('#flash').addClass('hidden');
    }

    // Dropdown Menu Chevron
    onClickDropDownLink(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;

        if($(clickedElement).attr('aria-expanded') == 'true')
        {
            $(clickedElement).find('.fa-caret-right').css({'rotate':'90deg'});
        }
        else
        {
            $(clickedElement).find('.fa-caret-right').css({'rotate':'0deg'});
        }
    }
}
