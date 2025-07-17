<?php

namespace Writeshh\Yarp\Tests\Feature;

use Illuminate\Support\Facades\File;
use Writeshh\Yarp\Tests\TestCase;

class RepositoryCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create the necessary directories for testing
        File::makeDirectory(base_path('app/Repositories'), 0755, true, true);
        File::makeDirectory(base_path('app/Models'), 0755, true, true);
        File::makeDirectory(base_path('app/Providers'), 0755, true, true);
        
        // Create a test model
        File::put(
            base_path('app/Models/Post.php'),
            <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected \$fillable = ['title', 'content'];
}
PHP
        );
    }
    
    protected function tearDown(): void
    {
        // Clean up test files and directories
        File::deleteDirectory(base_path('app'));
        
        parent::tearDown();
    }
    
    /** @test */
    public function it_can_generate_basic_repository()
    {
        $this->artisan('make:repo', ['name' => 'Post'])
            ->expectsOutput('Repository class created for model Post using basic type.')
            ->assertExitCode(0);
        
        $this->assertFileExists(base_path('app/Repositories/PostRepository.php'));
        $this->assertFileExists(base_path('app/Providers/RepositoryServiceProvider.php'));
        
        $repositoryContent = File::get(base_path('app/Repositories/PostRepository.php'));
        
        // Check if the repository has expected content
        $this->assertStringContainsString('namespace App\\Repositories;', $repositoryContent);
        $this->assertStringContainsString('use App\\Models\\Post;', $repositoryContent);
        $this->assertStringContainsString('class PostRepository implements RepositoryInterface', $repositoryContent);
    }
    
    /** @test */
    public function it_can_generate_extended_repository()
    {
        $this->artisan('make:repo', ['name' => 'Post', '--type' => 'extended'])
            ->expectsOutput('Repository class created for model Post using extended type.')
            ->assertExitCode(0);
        
        $this->assertFileExists(base_path('app/Repositories/PostRepository.php'));
        
        $repositoryContent = File::get(base_path('app/Repositories/PostRepository.php'));
        
        // Check if the repository has expected content
        $this->assertStringContainsString('namespace App\\Repositories;', $repositoryContent);
        $this->assertStringContainsString('use App\\Models\\Post;', $repositoryContent);
        $this->assertStringContainsString('class PostRepository extends BaseRepository', $repositoryContent);
    }
}
