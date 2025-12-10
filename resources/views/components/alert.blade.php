@if (session('success'))
    <script>
        (function() {
            if (!window._shownFlashMessages) {
                window._shownFlashMessages = new Set();
            }
            const message = '{{ addslashes(session('success')) }}';
            const messageKey = 'success:' + message;
            
            function showSuccessToast() {
                if (window._shownFlashMessages.has(messageKey)) {
                    return;
                }
                window._shownFlashMessages.add(messageKey);
                setTimeout(function() {
                    if (window.showToast) {
                        window.showToast(message, 'success', 5000);
                    }
                }, 100);
            }
            if (document.readyState === 'loading') {
                window.addEventListener('DOMContentLoaded', showSuccessToast, { once: true });
            } else {
                showSuccessToast();
            }
        })();
    </script>
@endif

@if (session('error'))
    <script>
        (function() {
            if (!window._shownFlashMessages) {
                window._shownFlashMessages = new Set();
            }
            const message = '{{ addslashes(session('error')) }}';
            const messageKey = 'error:' + message;
            
            function showErrorToast() {
                if (window._shownFlashMessages.has(messageKey)) {
                    return;
                }
                window._shownFlashMessages.add(messageKey);
                setTimeout(function() {
                    if (window.showToast) {
                        window.showToast(message, 'error', 5000);
                    }
                }, 100);
            }
            if (document.readyState === 'loading') {
                window.addEventListener('DOMContentLoaded', showErrorToast, { once: true });
            } else {
                showErrorToast();
            }
        })();
    </script>
@endif

@if (session('warning'))
    <script>
        (function() {
            if (!window._shownFlashMessages) {
                window._shownFlashMessages = new Set();
            }
            const message = '{{ addslashes(session('warning')) }}';
            const messageKey = 'warning:' + message;
            
            function showWarningToast() {
                if (window._shownFlashMessages.has(messageKey)) {
                    return;
                }
                window._shownFlashMessages.add(messageKey);
                setTimeout(function() {
                    if (window.showToast) {
                        window.showToast(message, 'warning', 5000);
                    }
                }, 100);
            }
            if (document.readyState === 'loading') {
                window.addEventListener('DOMContentLoaded', showWarningToast, { once: true });
            } else {
                showWarningToast();
            }
        })();
    </script>
@endif

@if (session('info'))
    <script>
        (function() {
            if (!window._shownFlashMessages) {
                window._shownFlashMessages = new Set();
            }
            const message = '{{ addslashes(session('info')) }}';
            const messageKey = 'info:' + message;
            
            function showInfoToast() {
                if (window._shownFlashMessages.has(messageKey)) {
                    return;
                }
                window._shownFlashMessages.add(messageKey);
                setTimeout(function() {
                    if (window.showToast) {
                        window.showToast(message, 'info', 5000);
                    }
                }, 100);
            }
            if (document.readyState === 'loading') {
                window.addEventListener('DOMContentLoaded', showInfoToast, { once: true });
            } else {
                showInfoToast();
            }
        })();
    </script>
@endif

@if (@$errors && $errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const errorMessages = @json($errors->all());
                errorMessages.forEach(function(error) {
                    if (window.showToast) {
                        window.showToast(error, 'error', 5000);
                    }
                });
            }, 100);
        });
    </script>
@endif
