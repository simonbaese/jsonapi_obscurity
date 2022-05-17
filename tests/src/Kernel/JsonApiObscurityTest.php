<?php

namespace Drupal\Tests\jsonapi_obscurity\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the JSON:API Obscurity functionality.
 *
 * @group jsonapi_obscurity
 */
class JsonApiObscurityTest extends KernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * The JSON:API obscurity prefix.
   *
   * @var string
   */
  protected $obscurityPrefix;

  /**
   * The JSON:API base path.
   *
   * @var string
   */
  protected $jsonApiBasePath;

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'filter',
    'jsonapi',
    'jsonapi_obscurity',
    'language',
    'node',
    'serialization',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    // Install schemas and configurations.
    $this->installSchema('system', ['sequences']);
    $this->installConfig('filter');
    $this->installConfig(['language']);
    $this->installEntitySchema('configurable_language');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    // Get JSON:API base path.
    $this->jsonApiBasePath = $this->container
      ->getParameter('jsonapi.base_path');

    // Set current user.
    $this->setUpCurrentUser([], [], TRUE);

    // Configure language negotiation via path prefix.
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => 'en', 'de' => 'de'])->save();
    $this->container->get('kernel')->rebuildContainer();
    $this->container->get('router.builder')->rebuild();
    $this->container->get('language_manager')->reset();

    // Create node of type page.
    NodeType::create(['type' => 'page'])->save();
    $this->node = $this->createNode();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $this->obscurityPrefix = '/' . $this->randomMachineName();
    $container->setParameter('jsonapi_obscurity.prefix', $this->obscurityPrefix);
  }

  /**
   * Tests the project issues fetcher.
   *
   * @throws \Exception
   */
  public function testJsonApiObscurity() {

    // Test without prefix.
    $path = $this->buildNodePath();
    $request = $this->getMockedRequest($path);
    $response = $this->processRequest($request);
    $this->assertEquals(404, $response->getStatusCode());

    // Test with correct prefix.
    $path = $this->buildNodePath($this->obscurityPrefix);
    $request = $this->getMockedRequest($path);
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());

    // Test with random prefix.
    $path = $this->buildNodePath('/' . $this->randomMachineName());
    $request = $this->getMockedRequest($path);
    $response = $this->processRequest($request);
    $this->assertEquals(404, $response->getStatusCode());

    // Test with random prefix and some 2-letter string.
    $path = $this->buildNodePath('/' . $this->randomMachineName(), '/xx');
    $request = $this->getMockedRequest($path);
    $response = $this->processRequest($request);
    $this->assertEquals(404, $response->getStatusCode());

    // Test with correct prefix and a langcode.
    $path = $this->buildNodePath($this->obscurityPrefix, '/de');
    $request = $this->getMockedRequest($path);
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());

    // Test with random prefix and a langcode.
    $path = $this->buildNodePath('/' . $this->randomMachineName(), '/de');
    $request = $this->getMockedRequest($path);
    $response = $this->processRequest($request);
    $this->assertEquals(404, $response->getStatusCode());
  }

  /**
   * Process a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Exception
   */
  protected function processRequest(Request $request): Response {
    return $this->container->get('http_kernel')->handle($request);
  }

  /**
   * Creates a GET request object.
   *
   * @param string $path
   *   The path.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function getMockedRequest(string $path): Request {
    $request = Request::create($path);
    $request->headers->set('Accept', 'application/vnd.api+json');
    return $request;
  }

  /**
   * Builds a node path.
   *
   * @param string $prefix
   *   The obscurity prefix for the path. Defaults to an empty string.
   * @param string $langcode
   *   The langcode with a leading slash. Defaults to an empty string.
   *
   * @return string
   *   The node path.
   */
  protected function buildNodePath(string $prefix = '', string $langcode = '') {
    return $prefix . $langcode . $this->jsonApiBasePath . '/node/' . $this->node->bundle() . '/' . $this->node->uuid();
  }

}
