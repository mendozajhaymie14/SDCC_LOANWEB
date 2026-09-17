<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SCC.AI — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  @include ('admin.css')
</head>
<body>

<!-- SIDEBAR -->
@include ('admin.sidebar')
<!-- MAIN WRAPPER -->
 <div class="main">
@include ('admin.body')
</div>
@include('admin.footer')

</body>
</html>