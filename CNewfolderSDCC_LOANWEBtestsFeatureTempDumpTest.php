<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TempDumpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dump_applications_index_html()
    {
        $officer = User::factory()->asLoanOfficer()->create();

        $member = CoopMember::factory()->create([
            'user_id' => $officer->id,
            'status' => CoopMember::STATUS_ACTIVE,
            'monthly_income' => 30000,
            'full_name' => 'Juan Santos',
            'email' => 'juan@example.com',
        ]);

        LoanApplication::factory()->create([
            'user_id' => $officer->id,
            'full_name' => 'Juan Santos',
            'loan_type' => 'regular',
            'amount' => 20000,
            'status' => 'pending',
            'monthly_income' => 30000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'term_months' => 36,
        ]);

        $response = $this->actingAs($officer)->get(route('applications.index'));

        $html = $response->getContent();
        file_put_contents('/tmp/dump.html', $html);

        $this->assertStringContainsString('modal-newApp', $html);
        $this->assertStringContainsString('applications.encode.store', $html);
        $this->assertStringContainsString('openEncodeModal', $html);
        $this->assertStringContainsString('#overlay', $html);

        echo "DUMPED HTML to /tmp/dump.html\n";
        echo "Contains modal-newApp: " . (strpos($html, 'modal-newApp') !== false ? 'YES' : 'NO') . "\n";
        echo "Contains encode.store: " . (strpos($html, 'applications.encode.store') !== false ? 'YES' : 'NO') . "\n";
        echo "Contains openEncodeModal: " . (strpos($html, 'openEncodeModal') !== false ? 'YES' : 'NO') . "\n";
    }
}
