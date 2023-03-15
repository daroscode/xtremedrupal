<?php

namespace Drupal\Tests\maxlength\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\editor\Entity\Editor;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests Javascript behaviour of Maxlength module with CKEditor.
 *
 * @group maxlength
 */
class MaxlengthCkeditorTest extends WebDriverTestBase {

  /**
   * The user to use during testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'maxlength',
    'text',
    'ckeditor5',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => [],
    ])->save();

    $this->user = $this->drupalCreateUser([
      'access administration pages',
      'administer entity_test content',
      'administer site configuration',
      'administer filters',
      'use text format full_html',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests the character count and limit works with CKEditor 5 version.
   */
  public function testCkeditor5() {
    Editor::create([
      'format' => 'full_html',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => [
            'heading',
            'bold',
            'italic',
            // Ensure we enable the source button for the test.
            'sourceEditing',
          ],
        ],
      ],
    ])->save();
    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'entity_test',
      'field_name' => 'foo',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'foo',
      'label' => 'Foo',
      'description' => 'Description of a text field',
    ])->save();
    $widget = [
      'type' => 'text_textarea_with_summary',
      'settings' => [
        'show_summary' => TRUE,
        'summary_rows' => 3,
      ],
      'third_party_settings' => [
        'maxlength' => [
          'maxlength_js' => 200,
          'maxlength_js_label' => 'Content limited to @limit characters, remaining: <strong>@remaining</strong> and total @count',
        ],
      ],
    ];
    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent('foo', $widget)
      ->save();

    $entity = EntityTest::create(['type' => 'entity_test', 'name' => 'Test']);
    $entity->save();

    $this->drupalLogin($this->user);
    $this->drupalGet($entity->toUrl('edit-form'));

    // Assert CKEditor5 is present.
    $settings = $this->getDrupalSettings();
    $this->assertContains('ckeditor5/internal.drupal.ckeditor5', explode(',', $settings['ajaxPageState']['libraries']), 'CKEditor5 glue library is present.');

    // Assert the maxlength counter labels.
    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: 200 and total 0');

    // Give maxlength.js some time to manipulate the DOM.
    $this->assertSession()->waitForElement('css', 'div.counter');

    // Check that only a counter div is found on the page.
    $this->assertSession()->elementsCount('css', 'div.counter', 1);

    // Check that the counter div follows the description of the field.
    $found = $this->xpath('//div[@data-drupal-selector="edit-foo-0"]/following-sibling::div[@id="edit-foo-0-value-counter"]');
    $this->assertCount(1, $found);

    // Add some text to the field and assert the maxlength counters changed accordingly.
    $this->enterTextInCkeditor('Foo', 'Some text with <strong>html</strong>');

    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: 181 and total 19');

    // Fill the body field with more characters than the limit.
    $this->enterTextInCkeditor('Foo', '<b>Lorem ipsum</b> dolor sit amet, <u>consectetur adipiscing</u> elit. Ut accumsan justo non interdum fermentum. Phasellus semper risus eu arcu eleifend dignissim. Class aptent taciti sociosqu ad litora erat curae. Extra characters');
    // The counter now should show "-17" for the extra characters.
    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: -17 and total 217');

    // Now change the maxlength configuration to use "Hard limit".
    $widget['third_party_settings']['maxlength']['maxlength_js_enforce'] = TRUE;
    $display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('entity_test.entity_test.default');
    $display->setComponent('foo', $widget)->save();

    // Reload the page.
    $this->getSession()->reload();
    // Fill the body field with more characters than the limit.
    $this->enterTextInCkeditor('Foo', '<b>Lorem ipsum</b> dolor sit amet, <br><u>consectetur adipiscing</u> elit. <img src=""><embed type="video/webm" src="">Ut accumsan justo non interdum fermentum. Phasellus semper risus eu arcu eleifend dignissim. Class aptent taciti sociosqu ad litora erat curae. Extra characters');
    // Assert the "Extra characters" string is truncated.
    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: 0 and total 200');
  }

  /**
   * Presses the given CKEditor button.
   *
   * @param string $field
   *   The label of the field to which the CKEditor is attached. For example
   *   'Body'.
   * @param string $button
   *   The title of the button to click.
   */
  protected function pressCkeditorButton(string $field, string $button): void {
    $wysiwyg = $this->getCkeditor($field);
    $button_elements = $this->getSession()->getDriver()->find($wysiwyg->getXpath() . '//button[@data-cke-tooltip-text="' . $button . '"]');
    if (empty($button_elements)) {
      throw new \Exception("Could not find the '$button' button.");
    }
    if (count($button_elements) > 1) {
      throw new \Exception("Multiple '$button' buttons found in the editor.");
    }
    $button = reset($button_elements);
    $button->click();
  }

  /**
   * Enters the given text in the textarea of the specified CKEditor.
   *
   * If there is any text existing it will be replaced.
   *
   * @param string $field
   *   The label of the field to which the CKEditor is attached. For example
   *   'Body'.
   * @param string $text
   *   The text to enter in the textarea.
   */
  protected function setCkeditorText(string $field, string $text): void {
    $wysiwyg = $this->getCkeditor($field);
    $textarea_elements = $this->getSession()->getDriver()->find($wysiwyg->getXpath() . '//textarea');
    if (empty($textarea_elements)) {
      throw new \Exception("Could not find the textarea for the '$field' field.");
    }
    if (count($textarea_elements) > 1) {
      throw new \Exception("Multiple textareas found for '$field'.");
    }
    $textarea = reset($textarea_elements);
    $textarea->setValue($text);
  }

  /**
   * Returns the CKEditor that is associated with the given field label.
   *
   * @param string $field
   *   The label of the field to which the CKEditor is attached.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The WYSIWYG editor.
   */
  protected function getCkeditor(string $field): NodeElement {
    $driver = $this->getSession()->getDriver();
    $label_elements = $driver->find('//label[text()="' . $field . '"]');
    if (empty($label_elements)) {
      throw new \Exception("Could not find the '$field' field label.");
    }
    if (count($label_elements) > 1) {
      throw new \Exception("Multiple '$field' labels found in the page.");
    }
    $wysiwyg_elements = $driver->find('//div[@id="edit-' . strtolower($field) . '-wrapper"]//div//div//div[contains(@class, " ck-editor ")]');
    if (empty($wysiwyg_elements)) {
      throw new \Exception("Could not find the '$field' wysiwyg editor.");
    }
    if (count($wysiwyg_elements) > 1) {
      throw new \Exception("Multiple '$field' wysiwyg editors found in the page.");
    }
    return reset($wysiwyg_elements);
  }

  /**
   * Enters the given text in the given CKEditor.
   *
   * @param string $label
   *   The label of the field containing the CKEditor.
   * @param string $text
   *   The text to enter in the CKEditor.
   */
  protected function enterTextInCkeditor(string $label, string $text): void {
    // If we are running in a JavaScript enabled browser, first click the
    // 'Source' button so we can enter the text as HTML and get the same result
    // as in a non-JS browser.
    $this->pressCkeditorButton($label, 'Source');
    $this->setCkeditorText($label, $text);
    // Make sure we switch back to normal view and let javascript to
    // execute filters on the text and validate the html.
    $this->pressCkeditorButton($label, 'Source');
    $this->getSession()->wait(2000);
  }

}
