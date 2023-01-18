import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    connect(e)
    {
        let uri = window.location.pathname;
        let isArticlePage = uri.match('/article/[0-9]+');

        if(isArticlePage != null)
        {
            $('body').addClass('form-control-bg-grey');
        }
    }
}