import './bootstrap.js';
import './styles/app.css';

import jquery from 'jquery';
const $ = jquery;
window.$ = window.jQuery = $;

window.showDiff = function(id) {
    var modal = $('#diffModalContent')[0];
    modal.innerHTML = $('#' + id)[0].innerHTML;
    $('#diffModal').modal('show');
};
