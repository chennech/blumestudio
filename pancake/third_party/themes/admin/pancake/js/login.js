function addEventListener(el, eventName, handler) {
    if (el.addEventListener) {
        el.addEventListener(eventName, handler);
    } else {
        el.attachEvent('on' + eventName, function () {
            handler.call(el);
        });
    }
}

var username = document.getElementById('username');
var fake_submit_button = document.getElementById("fake-submit-button");

if (username) {
    username.focus();
}

if (fake_submit_button) {
    addEventListener(fake_submit_button, "click", function (event) {
        event.preventDefault();
        document.getElementById('forgot-password-form').submit();
    });
}