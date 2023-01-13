import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    connect()
    {
        let uri = window.location.pathname;
        let isSearch = uri.match('/retail/search');
        let keywords = $.session.get('keywords');

        if(isSearch != null && 1 > 0)
        {
            if(keywords != 'undefined' && keywords != '')
            {
                this.getSearchResults(keywords,1);
            }
            else
            {
                this.getSearchResults('',1);
            }
        }
    }

    onSubmit(e)
    {
        e.preventDefault();

        let keywords = $('#search_field').val();

        this.getSearchResults(keywords, 1);
    }

    onPlusClick(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let element = $(clickedElement).prev('.prd-qty');
        let qty = parseInt(element.val());

        if(isNaN(qty))
        {
            qty = 1;
        }

        if(qty >= 1)
        {
            $(clickedElement).prevAll('.btn-minus').first().prop('disabled', false);
        }

        element.val(qty + 1);
        $(clickedElement).closest('.col-12').next().find('.btn-basket-add').attr('data-qty', qty + 1);
    }

    onMinusClick(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let element = $(clickedElement).next('.prd-qty');
        let qty = parseInt(element.val()) ? parseInt(element.val()) : 1;

        if(qty == 1)
        {
            $(clickedElement).prop('disabled', true);
        }
        else
        {
            element.val(qty - 1);
            $(clickedElement).closest('.col-12').next().find('.btn-basket-add').attr('data-qty', qty - 1);
        }
    }

    onPaginationClick(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let pageId = $(clickedElement).data('page-id');
        let keywords = $.session.get('keywords');

        this.getSearchResults(keywords, pageId);
    }

    getSearchResults(keywords, pageId)
    {
        let isValid = true;
        let self = this;

        if(keywords == 'undefined')
        {
            isValid = false;
        }

        if(isValid)
        {
            $.ajax({
                async: "true",
                url: "/retail/get/search",
                type: 'POST',
                dataType: 'json',
                data: {
                    'keywords': keywords,
                    'page-id': pageId,
                },
                beforeSend: function ()
                {
                    self.isLoading(true);

                    $('#main_nav').slideUp(700).removeClass('show');
                    window.scrollTo(0, 0);
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
                    $.session.set('keywords',keywords);
                    window.history.pushState(null, "Fluid", '/retail/search');
                    $('#retail_container').empty().append(response.html);
                    $('#search_field').val(keywords);
                    window.scrollTo(0, 0);
                    self.isLoading(false);
                    $.session.set('searchResults', response.html);
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