import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    connect(e)
    {
        let options = {
            title: 'Your Privacy',
            message: 'By clicking “Accept Cookies”, you agree Fluid Digital Wholesale can store cookies on your device and disclose information in accordance with our Cookie Policy.',
            delay: 600,
            expires: 1,
            link: '/article/3',
            onAccept: function(){
                var myPreferences = $.fn.ihavecookies.cookie();
                console.log('Yay! The following preferences were saved...');
                console.log(myPreferences);
            },
            uncheckBoxes: true,
            acceptBtnLabel: 'Accept Cookies',
            moreInfoLabel: 'Privacy Policy',
            cookieTypesTitle: 'Select which cookies you want to accept',
            fixedCookieTypeLabel: 'Essential',
            fixedCookieTypeDesc: 'These are essential for the website to work correctly.'
        }

        $('#necessary_msg').hide();
        $('body').ihavecookies(options);

        if ($.fn.ihavecookies.preference('marketing') === true) {
            console.log('This should run because marketing is accepted.');
        }

        $('#ihavecookiesBtn').on('click', function(){
            $('body').ihavecookies(options, 'reinit');
        });
    }

    onClickNCookieType()
    {
        $('#necessary_msg').slideToggle(700);
    }
}