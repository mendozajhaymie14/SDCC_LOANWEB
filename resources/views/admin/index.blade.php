<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
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