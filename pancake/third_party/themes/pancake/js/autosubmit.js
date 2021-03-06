var matches = function (el, selector) {
    var _matches = (el.matches || el.matchesSelector || el.msMatchesSelector || el.mozMatchesSelector || el.webkitMatchesSelector || el.oMatchesSelector);

    if (_matches) {
        return _matches.call(el, selector);
    } else {
        var nodes = el.parentNode.querySelectorAll(selector);
        for (var i = nodes.length; i--;) {
            if (nodes[i] === el)
                return true;
        }
        return false;
    }
};

if (matches(document.getElementsByTagName('body')[0], '.autosubmit')) {
    var forms = document.getElementsByTagName('FORM');
    for (var i = 0; i < forms.length; i++) {
        forms[i].submit();
    }
}