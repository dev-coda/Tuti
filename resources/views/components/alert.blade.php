@if (session('success'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (window.showToast) {
                    window.showToast('{{ addslashes(session('success')) }}', 'success', 5000);
                }
            }, 100);
        });
    </script>
@endif

@if (session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (window.showToast) {
                    window.showToast('{{ addslashes(session('error')) }}', 'error', 5000);
                }
            }, 100);
        });
    </script>
@endif

@if (session('warning'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (window.showToast) {
                    window.showToast('{{ addslashes(session('warning')) }}', 'warning', 5000);
                }
            }, 100);
        });
    </script>
@endif

@if (session('info'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (window.showToast) {
                    window.showToast('{{ addslashes(session('info')) }}', 'info', 5000);
                }
            }, 100);
        });
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
