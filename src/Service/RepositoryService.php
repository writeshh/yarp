<?php

namespace Writeshh\Yarp\Service;

class RepositoryService
{
    protected static function getStubs($type)
    {
        // Try to get from published stubs first
        $publishedStubPath = resource_path("vendor/writeshh/stubs/$type.stub");
        
        // If the published stub doesn't exist, use the one in the package
        if (file_exists($publishedStubPath)) {
            return file_get_contents($publishedStubPath);
        }
        
        // Fall back to the package stubs
        return file_get_contents(__DIR__ . "/../resources/stubs/$type.stub");
    }

    public static function ImplementNow($name, $type = 'basic')
    {
        if (!file_exists($path = base_path('/app/Repositories')))
            mkdir($path, 0777, true);

        // Create the repository class based on type
        if ($type == 'basic') {
            self::MakeRepositoryClass($name);
        } else {
            self::MakeBaseRepositoryClass($name);
        }

        // Create service provider binding if it doesn't exist yet
        self::RegisterServiceProvider($name);
    }

    protected static function MakeRepositoryClass($name)
    {
        $template = str_replace(
            ['{{modelName}}'],
            [$name],
            self::getStubs('Repository')
        );

        file_put_contents(base_path("/app/Repositories/{$name}Repository.php"), $template);
        
        return "Created {$name}Repository implementing RepositoryInterface directly";
    }
    
    protected static function MakeBaseRepositoryClass($name)
    {
        $template = str_replace(
            ['{{modelName}}'],
            [$name],
            self::getStubs('BaseRepository')
        );

        file_put_contents(base_path("/app/Repositories/{$name}Repository.php"), $template);
        
        return "Created {$name}Repository extending BaseRepository";
    }
    
    protected static function RegisterServiceProvider($name)
    {
        $providerPath = base_path('/app/Providers/RepositoryServiceProvider.php');
        
        // If the service provider doesn't exist, create it
        if (!file_exists($providerPath)) {
            // Create the directory if it doesn't exist
            if (!file_exists(dirname($providerPath))) {
                mkdir(dirname($providerPath), 0777, true);
            }
            
            $template = str_replace(
                ['{{bindings}}'],
                ["        \$this->app->bind(\\App\\Repositories\\{$name}Repository::class, \\App\\Repositories\\{$name}Repository::class);"],
                self::getStubs('ServiceProvider')
            );
            
            file_put_contents($providerPath, $template);
            
            // Remind to register the service provider in config/app.php
            echo "Remember to register App\\Providers\\RepositoryServiceProvider::class in config/app.php\n";
            
            return "Created RepositoryServiceProvider with {$name}Repository binding";
        }
        
        // If it exists, add a new binding if not already present
        $contents = file_get_contents($providerPath);
        $binding = "        \$this->app->bind(\\App\\Repositories\\{$name}Repository::class, \\App\\Repositories\\{$name}Repository::class);";
        
        // Check if the binding already exists
        if (strpos($contents, "{$name}Repository::class") === false) {
            // Find the last binding line and add after it
            $registerPos = strpos($contents, 'public function register');
            if ($registerPos !== false) {
                $closeBracePos = strpos($contents, '}', $registerPos);
                $newContents = substr($contents, 0, $closeBracePos) . "\n{$binding}\n" . substr($contents, $closeBracePos);
                file_put_contents($providerPath, $newContents);
                return "Added {$name}Repository binding to RepositoryServiceProvider";
            }
        }
        
        return "Binding for {$name}Repository already exists";
    }
}
