function processForm(form) {

    const optin = document.querySelector('#enewsletter-optin').checked;
    const optinErrMsg = document.querySelector('#enewsletter-form .optin-error-msg');
    
    if (!optinErrMsg.classList.contains('hidden')) {
        optinErrMsg.classList.add('hidden');
    }

    if (!optin) {
        optinErrMsg.classList.remove('hidden');
        return;
    }

    let authData = {
        email: document.querySelector('#enewsletter-email').value
    };

    let contactData = {
        channels: {
            email: {
                subscribeStatus: 'subscribed'
            }
        }
    };

    if (window.location.host === 'web-staging.sleepscore.com') {
        contactData['Website-SignUp-Welcome'] = true;
    } else {
        contactData['testlist'] = true;
    }

    crdl('contact', authData, contactData);

    document.querySelector('#enewsletter-confirmation').classList.remove('hidden');

}

function initNewsletter() {

    const form = document.querySelector('#enewsletter-form');

    if (typeof crdl === 'undefined') {
        form.querySelector('fieldset').setAttribute('disabled', true);
        document.querySelector('#enewsletter-consent-msg').classList.remove('hidden');
        document.querySelector('#enewsletter-form .form-group').classList.add('hidden');
    } else {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            processForm(form);
        });
    }

}

$(document).ready(function() {
    setTimeout(() => {
        initNewsletter();
    }, 3000);
});
