<?php

/**
 * @file
 * Unit tests for the override_node_options module.
 */

namespace Drupal\override_node_options\Tests;

use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Unit tests for the override_node_options module.
 *
 * @group override_node_options
 */
class OverrideNodeOptionsTest extends WebTestBase {

  protected $normalUser;

  protected $adminUser;

  /**
   * A node to test against.
   *
   * @var NodeInterface $node
   */
  protected $node;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['override_node_options'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $types = NodeType::loadMultiple();
    if (empty($types['article'])) {
      $this->drupalCreateContentType(['type' => 'page', 'name' => t('Page')]);
    }

    $this->normalUser = $this->drupalCreateUser(
      [
        'create page content',
        'edit any page content',
      ]
    );
    $this->node = $this->drupalCreateNode();
  }

  /**
   * Assert that fields in a node were updated to certail values.
   *
   * @param NodeInterface $node
   *   The node object to check (will be reloaded from the database).
   * @param array $fields
   *   An array of values to check equality, keyed by node object property.
   */
  public function assertNodeFieldsUpdated(NodeInterface $node, array $fields) {
    // Re-load the node from the database to make sure we have the current
    // values.
    $node = node_load($node->id(), NULL, TRUE);
    foreach ($fields as $field => $value) {
      $this->assertEqual(
        $node->$field,
        $value,
        $this->t('Node @field was updated to !value, expected !expected.',
          [
            '@field' => $field,
            '!value' => var_export($node->$field, TRUE),
            '!expected' => var_export($value, TRUE),
          ]
        )
      );
    }
  }

  /**
   * Assert that the user cannot access fields on node add and edit forms.
   *
   * @param NodeInterface $node
   *   The node object, will be used on the node edit form.
   * @param array $fields
   *   An array of form fields to check.
   */
  public function assertNodeFieldsNoAccess(NodeInterface $node, array $fields) {
    $this->drupalGet('node/add/' . $node->type);
    foreach ($fields as $field) {
      $this->assertNoFieldByName($field);
    }

    $this->drupalGet('node/' . $this->node->id() . '/edit');
    foreach ($fields as $field) {
      $this->assertNoFieldByName($field);
    }
  }

  /**
   * Test the 'Authoring information' fieldset.
   */
  public function testNodeOptions() {
    $this->adminUser = $this->drupalCreateUser([
      'create page content',
      'edit any page content',
      'override page published option',
      'override page promote to front page option',
      'override page sticky option',
    ]);
    $this->drupalLogin($this->adminUser);

    $fields = array(
      'status' => (bool) !$this->node->status,
      'promote' => (bool) !$this->node->promote,
      'sticky' => (bool) !$this->node->sticky,
    );
    $this->drupalPostForm('node/' . $this->node->id() . '/edit', $fields, t('Save'));
    $this->assertNodeFieldsUpdated($this->node, $fields);

    $this->drupalLogin($this->normalUser);
    $this->assertNodeFieldsNoAccess($this->node, array_keys($fields));
  }

  /**
   * Test the 'Revision information' fieldset.
   */
  public function testNodeRevisions() {
    $this->adminUser = $this->drupalCreateUser(
      [
        'create page content',
        'edit any page content',
        'override page revision option',
      ]
    );
    $this->drupalLogin($this->adminUser);

    $fields = array(
      'revision' => TRUE,
    );

    $this->drupalPostForm('node/' . $this->node->id() . '/edit', $fields, t('Save'));
    $this->assertNodeFieldsUpdated($this->node, array('vid' => $this->node->vid));

    $this->drupalLogin($this->normalUser);
    $this->assertNodeFieldsNoAccess($this->node, array_keys($fields));
  }

  /**
   * Test the 'Authoring information' fieldset.
   */
  public function testNodeAuthor() {
    $this->adminUser = $this->drupalCreateUser(
      [
        'create page content',
        'edit any page content',
        'override page authored on option',
        'override page authored by option',
      ]
    );
    $this->drupalLogin($this->adminUser);

    $this->drupalPostForm('node/' . $this->node->id() . '/edit', array('name' => 'invalid-user'), t('Save'));
    $this->assertText('The username invalid-user does not exist.');

    $this->drupalPostForm('node/' . $this->node->id() . '/edit', array('date' => 'invalid-date'), t('Save'));
    $this->assertText('You have to specify a valid date.');

    $time = time();
    $fields = [
      'name' => '',
      'date' => format_date($time, 'custom', 'Y-m-d H:i:s O'),
    ];
    $this->drupalPostForm('node/' . $this->node->id() . '/edit', $fields, t('Save'));
    $this->assertNodeFieldsUpdated($this->node, array('uid' => 0, 'created' => $time));

    $this->drupalLogin($this->normalUser);
    $this->assertNodeFieldsNoAccess($this->node, array_keys($fields));
  }
}
