import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    connect()
    {
        let uri = window.location.pathname;
        let isBasket = uri.match('/retail/basket');

        if(isBasket != null)
        {
            this.getBasket($(this.element).attr('data-basket-id'));
        }
    }

    onBasketClick(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let basketId = $(clickedElement).attr('data-basket-id');

        this.getBasket(basketId);
    }

    onAddToClick(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let clinicId = $(clickedElement).attr('data-clinic-id');
        let productId = $(clickedElement).attr('data-product-id');
        let qty = $(clickedElement).attr('data-qty');
        let price = $(clickedElement).attr('data-price');
        let self = this;
        let isValid = true;

        if(productId == '' || productId == 'undefined' || qty == '' || qty == 'undefined' || price == '' || price == 'undefined')
        {
            isValid = false;
        }

        if(isValid)
        {
            $.ajax({
                async: "true",
                url: "/retail/add-to-basket",
                type: 'POST',
                dataType: 'json',
                data: {
                    'clinic-id': clinicId,
                    'product-id': productId,
                    'qty': qty,
                    'price': price,
                },
                beforeSend: function ()
                {
                    self.isLoading(true);
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
                    self.getFlash(response.flash);
                    self.isLoading(false);
                }
            });
        }
        else
        {
            getFlash('An Error Occurred','danger');
        }
    }

    onClickBackToSearch(e)
    {
        e.preventDefault();

        $('#retail_container').empty().append($.session.get('searchResults'));
        window.history.pushState(null, "Fluid", '/retail/search');
        window.scrollTo(0,0);
    }

    onClickClearBasket(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let basketId = $(clickedElement).data('basket-id');
        let self = this;

        if(basketId > 0)
        {
            $.ajax({
                async: "true",
                url: "/clinics/inventory/inventory-clear-basket",
                type: 'POST',
                dataType: 'json',
                data: {
                    'basket-id': basketId
                },
                beforeSend: function ()
                {
                    self.isLoading(true);
                },
                success: function (response)
                {
                    self.getBasket(basketId);
                }
            });
        }
    }

    onClickRemoveItem(e)
    {
        e.preventDefault();

        let clickedElement = e.currentTarget;
        let itemId = $(clickedElement).attr('data-item-id');
        let self = this;

        if(itemId > 0)
        {
            $.ajax({
                async: "true",
                url: "/retail/inventory/inventory-remove-basket-item",
                type: 'POST',
                dataType: 'json',
                data: {
                    'item-id': itemId
                },
                beforeSend: function ()
                {
                    self.isLoading(true);
                },
                complete: function(e)
                {
                    if(e.status === 500){
                        window.location.href = '/retail/error';
                    }
                },
                success: function (response)
                {
                    self.getBasket(response.basketId);
                    self.getFlash(response.message);
                    self.isLoading(false);
                }
            });
        }
    }

    onClickPrintBasket(e)
    {
        $('.basket-qty').each(function ()
        {
            $(this).replaceWith($(this).val())
        });

        $('#btn_checkout').hide();

        let basket = $('#basket_items').html();
        let htm = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Fluid Commerce</title>';
        htm += '<link rel="stylesheet" media="all" href="/css/bootstrap.min.css"><link rel="stylesheet" media="all" href="/css/style.min.css">';
        htm += '<style type="text/css" media="print">@page { size: landscape; }</style>';
        htm += '<link rel="stylesheet" href="/css/fontawesome/css/all.min.css"></head><body><div class="container-fluid">'+ basket;
        htm += '</container></body></html>';

        let w = window.open( '', "Customer Listing", "menubar=0,location=0,height=670,width=700" );
        w.document.write(htm);

        setTimeout(function(){w.print();w.close();},1000);
    }

    onChangeQty(e)
    {
        let self = this;
        let element = e.currentTarget;
        let itemId = $(element).attr('data-basket-item-id');
        let qty = $(element).val();
        let isValid = true;
        let errorQty = $(element).next('.hidden_msg');
        let checkoutBtn = $('#btn_checkout');

        errorQty.hide();
        checkoutBtn.show();

        if(qty <= 0)
        {
            errorQty.empty().append('Please select a positive value.').show();
            checkoutBtn.hide();
            isValid = false;
        }

        if(isValid) {

            $.ajax({
                async: "true",
                url: "/retail/update-basket",
                type: 'POST',
                dataType: 'json',
                data: {
                    'item-id': itemId,
                    qty: qty
                },
                beforeSend: function ()
                {
                    self.isLoading(true);
                },
                success: function (response)
                {
                    $(element).closest('.col-4').next().empty().append(response.total);
                    $(element).closest('.col-cell').next().find('h5').empty().append(response.subTotal);
                    self.getFlash(response.flash);
                    self.isLoading(false);
                }
            });
        }
    }

    getBasket(basketId)
    {
        let self = this;

        $.ajax({
            async: "true",
            url: "/retail/get-basket",
            type: 'POST',
            dataType: 'json',
            data: {
                'basket-id': basketId
            },
            beforeSend: function ()
            {
                self.isLoading(true);
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
                $('#retail_container').empty().append(response.html);
                $('#half_border_row').removeClass('px-0 px-sm-2');
                window.history.pushState(null, "Fluid", '/retail/basket');
                self.isLoading(false);
            }
        });
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

    popOver()
    {
        let popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        let popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        })
    }
}