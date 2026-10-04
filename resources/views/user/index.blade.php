<!DOCTYPE html>
<html lang="en">
<head>
  @include('user.css')
  <title>My Account — San Dionisio Credit Cooperative</title>
</head>
<body>

  <header>
    @include('user.header')
  </header>

  @include('user.sidebar')

  <main class="member-main">
    @include('user.flash')
    @include('user.body')
  </main>

  @include('user.scripts')

</body>
</html>