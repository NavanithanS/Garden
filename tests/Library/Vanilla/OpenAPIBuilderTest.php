<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GPLv2.0-only
 */

namespace VanillaTests\Library\Vanilla;

use PHPUnit\Framework\TestCase;
use Vanilla\Addon;
use Vanilla\AddonManager;
use Vanilla\OpenAPIBuilder;
use VanillaTests\Fixtures\MockAddonManager;
use VanillaTests\Fixtures\Request;

/**
 * Test Vanilla's OpenAPI build.
 */
class OpenAPIBuilderTest extends TestCase {

    /**
     * Test that we generate a proper base path.
     */
    public function testApiPathGeneration() {
        $addonManager = new AddonManager();
        $request = new Request();
        $request->setAssetRoot('root');
        $request->setHost('testhost.com');
        $request->setScheme('https');
        $builder = new OpenAPIBuilder($addonManager, $request);
        $data = $builder->generateFullOpenAPI();
        $this->assertEquals(
            'https://testhost.com/root/api/v2',
            $data['servers'][0]['url'],
            'Generated API base was incorrect'
        );
    }

    /**
     * The OpenAPI build should validate against the OpenAPI spec.
     */
    public function testValidOpenAPI() {
        $am = new AddonManager(
            [
                Addon::TYPE_ADDON => ['/applications', '/plugins'],
                Addon::TYPE_THEME => '/themes',
                Addon::TYPE_LOCALE => '/locales'
            ],
            PATH_ROOT.'/tests/cache/open-api-builder/vanilla-manager'
        );

        $request = new Request();
        $builder = new OpenAPIBuilder($am, $request, PATH_ROOT.'/tests/cache/'.__FUNCTION__.'.php');

        $data = $builder->generateFullOpenAPI();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $path = PATH_ROOT.'/tests/cache/open-api-builder/openapi.json';
        file_put_contents($path, $json);

        $dir = getcwd();
        chdir(PATH_ROOT);
        exec("npx swagger-cli@2.3.4 validate $path", $output, $result);
        chdir($dir);
        $this->assertSame(0, $result, $path);
    }

    /**
     * Test specific OpenAPI schema merging bugs.
     *
     * @param array $schema1
     * @param array $schema2
     * @param array $expected
     * @dataProvider provideSchemaMergeScenarios
     */
    public function testSchemaMergeBugs(array $schema1, array $schema2, array $expected) {
        $actual = OpenAPIBuilder::mergeSchemas($schema1, $schema2);
        $this->assertSame($expected, $actual);
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function provideSchemaMergeScenarios(): array {
        $r = [
            'enum' => [
                ['enum' => ['a', 'c']], ['enum' => ['a', 'b']], ['enum' => ['a', 'b', 'c']]
            ],
            'parameters' => [
                ['parameters' => [['name' => 'a', 'e' => [1]]]],
                ['parameters' => [['name' => 'b'], ['name' => 'a', 'e' => [2]]]],
                ['parameters' => [['name' => 'a', 'e' => [1, 2]], ['name' => 'b']]],
            ],
            'required' => [
                ['required' => ['a', 'c']], ['required' => ['a', 'b']], ['required' => ['a', 'c', 'b']]
            ],
        ];
        return $r;
    }
}
