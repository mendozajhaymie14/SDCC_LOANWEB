<?php
$app = require /app.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
foreach ($connection->getSchema() as $name => $t) {
    if (str_contains($name, 'borrower')) {
        echo $t->toSql(), "\n";
    }
}
