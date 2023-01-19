/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
const $ = require('jquery');
global.$ = global.jQuery = $;
import 'slick-carousel';
import * as bootstrap from 'bootstrap/dist/js/bootstrap.bundle';
window.bootstrap = bootstrap;
import intlTelInput from  'intl-tel-input' ;
import  'intl-tel-input/build/css/intlTelInput.css' ;
window [ 'intlTelInput' ] = intlTelInput;
import 'jquery-scrollTo';
import 'popper.js';

// start the Stimulus application
import './bootstrap';
