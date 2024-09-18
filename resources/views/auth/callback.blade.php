<!-- resources/views/auth/callback.blade.php -->

<!DOCTYPE html>
<html>
<head>
    <title>OAuth Callback</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="container">
        @if ($status === 'success')
            <div class="alert alert-success">
                {{ $message }}
            </div>
        @elseif ($status === 'error')
            <div class="alert alert-danger">
                {{ $message }}
            </div>
        @endif
    </div>
</body>
</html>
