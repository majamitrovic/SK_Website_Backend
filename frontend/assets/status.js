(function () {
    var apiBaseUrl = ((window.CHECKOUT_CONFIG || {}).apiBaseUrl || 'http://localhost:8001/api').replace(/\/$/, '');
    var form = document.getElementById('status-form');
    var input = document.getElementById('merchant_transaction_id');
    var output = document.getElementById('status-output');
    var list = document.getElementById('status-list');

    function setOutput(message, type) {
        output.textContent = message || '';
        output.className = 'status' + (type ? ' ' + type : '');
    }

    function row(label, value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return '';
        }

        return '<div><dt>' + label + '</dt><dd>' + String(value).replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        }) + '</dd></div>';
    }

    function render(status) {
        var html = '';
        html += row('Status', status.transactionStatus);
        html += row('Success', status.success ? 'Yes' : 'No');
        html += row('UUID', status.uuid);
        html += row('Purchase ID', status.purchaseId);
        html += row('Payment method', status.paymentMethod);
        html += row('Amount', status.amount && status.currency ? status.amount + ' ' + status.currency : '');

        (status.schedules || []).forEach(function (schedule) {
            html += row('Schedule ID', schedule.scheduleId);
            html += row('Schedule status', schedule.scheduleStatus);
            html += row('Next charge', schedule.scheduledAt);
        });

        list.innerHTML = html;
    }

    function loadStatus() {
        var tx = input.value.trim();
        if (!tx) {
            setOutput('Enter a merchant transaction ID.', 'error');
            return;
        }

        setOutput('Checking status...', 'pending');
        list.innerHTML = '';

        fetch(apiBaseUrl + '/status.php?merchant_transaction_id=' + encodeURIComponent(tx), {
            headers: { Accept: 'application/json' }
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload.ok) {
                        throw payload;
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                setOutput('');
                render(payload.status || {});
            })
            .catch(function (error) {
                setOutput(error.message || 'Status request failed.', 'error');
            });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadStatus();
    });

    input.value = new URLSearchParams(window.location.search).get('merchant_transaction_id') || '';
    if (input.value) {
        loadStatus();
    }
}());
