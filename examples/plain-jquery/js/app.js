$(document).ready(function () {
    const config = {
        clientId: 'test_plain_jquery',
        redirectUri: 'http://localhost:37184',
        authorizationEndpoint: 'https://idp.amtgard.com/oauth/authorize',
        // Use proxy for token endpoint
        tokenEndpoint: '/api/oauth/token',
        scopes: 'profile email'
    };

    const auth = new OAuthClient(config);

    // Initial check for callback
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('code')) {
        auth.handleCallback()
            .then(() => {
                updateUI();
            })
            .catch(err => {
                console.error('Login Failed', err);
                $('#validation-result')
                    .show()
                    .css({ backgroundColor: '#ffe6e6', border: '1px solid #ffcccc' })
                    .html(`<strong>Login Failed:</strong> ${err.message}`);
            });
    } else {
        updateUI();
    }

    function updateUI() {
        if (auth.isLoggedIn()) {
            $('#status-loggedin').text('Yes').removeClass('no').addClass('yes');
            $('#btn-login').hide();
            $('#btn-logout, #btn-validate').show();
            $('#user-info').show();
        } else {
            $('#status-loggedin').text('No').removeClass('yes').addClass('no');
            $('#btn-login').show();
            $('#btn-logout, #btn-validate, #user-info').hide();
        }
    }

    $('#btn-login').on('click', function () {
        auth.login();
    });

    $('#btn-logout').on('click', function () {
        auth.logout();
    });

    $('#btn-validate').on('click', function () {
        const resultDiv = $('#validation-result');
        resultDiv.hide();

        const token = auth.getAccessToken();

        $.ajax({
            url: '/api/resources/userinfo', // Proxy
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            success: function (data) {
                resultDiv.show()
                    .css({ backgroundColor: '#e6ffe6', border: '1px solid #ccffcc' })
                    .html('<strong>Validation Result:</strong> Success<br><pre style="font-size: 0.8rem; overflow-x: auto;">' + JSON.stringify(data, null, 2) + '</pre>');
            },
            error: function (xhr) {
                resultDiv.show()
                    .css({ backgroundColor: '#ffe6e6', border: '1px solid #ffcccc' })
                    .html(`<strong>Validation Result:</strong> Failed<br><span style="color: red;">${xhr.statusText}</span>`);
            }
        });
    });
});
