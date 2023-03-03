<?php

namespace Writeshh\Yarp\Service;

class RepositoryService
{
    protected static function getStubs($type)
    {
        return file_get_contents(resource_path("vendor/salmanzafar/stubs/$type.stub"));
    }

    public static function ImplementNow($name)
    {
        if (!file_exists($path = base_path('/app/Repositories')))
            mkdir($path, 0777, true);

        self::MakeRepositoryClass($name);
    }

    protected static function MakeRepositoryClass($name)
    {
        $template = str_replace(
            ['{{modelName}}'],
            [$name],
            self::GetStubs('Repository')
        );

        file_put_contents(base_path("/app/Repositories/{$name}Repository.php"), $template);
    }
}
