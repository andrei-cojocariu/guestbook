// Guest Book init — externalized from the inline theme <script> so the CSP
// can serve scripts from 'self' only (no 'unsafe-inline'). GB2-08.
$(function () {
    if ($.fn.placeholder) {
        $('input, textarea').placeholder();
    }
});
