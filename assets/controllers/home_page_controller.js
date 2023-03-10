import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    connect()
    {
        $('.multiple-items').slick({
            infinite: true,
            slidesToShow: 3,
            slidesToScroll: 1,
            dots: false,
            responsive: [
                {
                    breakpoint: 768,
                    settings: {
                        arrows: false,
                        centerMode: true,
                        centerPadding: '40px',
                        slidesToShow: 3
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        arrows: false,
                        centerMode: true,
                        centerPadding: '40px',
                        slidesToShow: 1
                    }
                }
            ]
        });


        if(window.location.origin == window.location.href.replace(/\/+$/, '')){

            $('body').removeClass('frontend-body-pt');
        }


        let urlParams = new URLSearchParams(window.location.search);

        if(urlParams.has('contact'))
        {
            $('body').removeClass('frontend-body-pt');
            document.querySelector('#contact_title').scrollIntoView({
                behavior: 'smooth'
            });

            window.history.pushState(null, "Fluid", '/');
        }

        let uri = window.location.pathname;
        let isSellersPage = uri.match('/sellers');

        if(isSellersPage)
        {
            $('body').addClass('form-control-bg-grey');
        }
    }

    onClickContactLink(e)
    {
        e.preventDefault();

        if(window.location.origin == window.location.href.replace(/\/+$/, ''))
        {
            $('body').removeClass('frontend-body-pt');
            document.querySelector('#contact_title').scrollIntoView({
                behavior: 'smooth'
            });

        } else {

            window.location.href = window.location.protocol +'//'+ window.location.host + '/?contact';
        }
    }

    onSubmitContactForm(e)
    {
        e.preventDefault();

        let data = new FormData(this);
        let name = $('#name').val();
        let telephone = $('#telephone').val();
        let email =  $('#email').val();
        let message = $('#message').val();
        let error_name = $('#error_name');
        let error_telephone = $('#error_telephone');
        let error_email = $('#error_email');
        let error_message = $('#error_message');
        let isValid = true;
        let self = this;

        error_name.hide()
        error_telephone.hide()
        error_email.hide()
        error_message.hide()

        if(name == '' || name == 'undefined'){

            error_name.show();
            isValid = false;
        }

        if(telephone == '' || telephone == 'undefined'){

            error_telephone.show();
            isValid = false;
        }

        if(email == '' || email == 'undefined'){

            error_email.show();
            isValid = false;
        }

        if(message == '' || message == 'undefined'){

            error_message.show();
            isValid = false;
        }

        if(isValid) {

            $.ajax({
                url: "/contact-form",
                type: 'POST',
                contentType: false,
                processData: false,
                cache: false,
                timeout: 600000,
                dataType: 'json',
                data: data,
                beforeSend: function () {

                    self.isLoading(true);
                    $(window).scrollTop(0);
                },
                complete: function(e, xhr, settings){
                    if(e.status === 500){
                        window.location.href = '/retail/error';
                    }
                },
                success: function (response) {

                    self.getFlash(response);
                    self.isLoading(false);
                    console.log(response);
                }
            });
        }
    }

    getFlash(flash)
    {
        $('#flash').addClass('alert-success').removeClass('alert-danger').addClass('alert').addClass('text-center');
        $('#flash').removeClass('users-flash').addClass('users-flash').empty().append(flash).removeClass('hidden');

        setTimeout(function() {
            $('#flash').removeClass('alert-success').removeClass('alert').removeClass('text-center');
            $('#flash').removeClass('users-flash').empty().addClass('hidden');
        }, 5000);
    }

    isLoading(status)
    {
        if(status) {

            $("div.spanner").addClass("show");
            $("div.overlay").addClass("show");

        } else {

            $("div.spanner").removeClass("show");
            $("div.overlay").removeClass("show");
        }
    }
}
