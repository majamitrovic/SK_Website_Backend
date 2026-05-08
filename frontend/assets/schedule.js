(function () {
    var apiBaseUrl = ((window.CHECKOUT_CONFIG || {}).apiBaseUrl || 'http://localhost:8001/api').replace(/\/$/, '');
    var form = document.getElementById('schedule-form');
    var actions = document.getElementById('schedule-actions');
    var input = document.getElementById('schedule_id');
    var actionScheduleId = document.getElementById('action_schedule_id');
    var continueInput = document.getElementById('continue_datetime');
    var output = document.getElementById('schedule-output');
    var list = document.getElementById('schedule-list');

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    function setOutput(message, type) {
        output.textContent = message || '';
        output.className = 'status' + (type ? ' ' + type : '');
    }

    function row(label, value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return '';
        }
        return '<div><dt>' + label + '</dt><dd>' + escapeHtml(value) + '</dd></div>';
    }

    function defaultContinueDate() {
        var date = new Date(Date.now() + 60 * 60 * 1000);
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        return date.toISOString().slice(0, 16);
    }

    function render(schedule) {
        var html = '';
        html += row('Success', schedule.success ? 'Yes' : 'No');
        html += row('Schedule ID', schedule.scheduleId);
        html += row('Registration UUID', schedule.registrationUuid);
        html += row('Old status', schedule.oldStatus);
        html += row('New status', schedule.newStatus);
        html += row('Next charge', schedule.scheduledAt);
        html += row('Error', schedule.errorMessage ? schedule.errorMessage + ' ' + schedule.errorCode : '');
        list.innerHTML = html;
    }

    function requestSchedule(method, body) {
        var options = { method: method, headers: { Accept: 'application/json' } };
        if (body) {
            options.body = new FormData(body);
        }

        var url = apiBaseUrl + '/schedule.php';
        if (method === 'GET') {
            url += '?schedule_id=' + encodeURIComponent(input.value.trim());
        }

        return fetch(url, options)
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload.ok) {
                        throw payload;
                    }
                    return payload;
                });
            });
    }

    function showSchedule() {
        if (!input.value.trim()) {
            setOutput('Enter a schedule ID.', 'error');
            return;
        }

        actionScheduleId.value = input.value.trim();
        setOutput('Loading schedule...', 'pending');
        list.innerHTML = '';

        requestSchedule('GET')
            .then(function (payload) {
                setOutput('');
                render(payload.schedule || {});
            })
            .catch(function (error) {
                setOutput(error.message || 'Schedule request failed.', 'error');
            });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        showSchedule();
    });

    actions.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!actionScheduleId.value.trim()) {
            actionScheduleId.value = input.value.trim();
        }
        setOutput('Updating schedule...', 'pending');
        requestSchedule('POST', actions)
            .then(function (payload) {
                setOutput('');
                render(payload.schedule || {});
            })
            .catch(function (error) {
                setOutput(error.message || 'Schedule update failed.', 'error');
            });
    });

    input.value = new URLSearchParams(window.location.search).get('schedule_id') || '';
    actionScheduleId.value = input.value;
    continueInput.value = defaultContinueDate();
    if (input.value) {
        showSchedule();
    }
}());
