<?php

use Civi\Api4\UFField;
use Civi\Core\HookInterface;
use Civi\Test\EntityTrait;
use Civi\Test\FormTrait;
use Civi\Test\FormWrapper;
use Civi\Test\HeadlessInterface;

/**
 * The legacyprofiles buildForm guard for profile delete and disable.
 *
 * @group headless
 */
class CRM_Legacyprofiles_BuildFormTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {

  use EntityTrait;
  use FormTrait;

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    // The unit-test hook caches its module list. Replace it after installMe().
    \CRM_Utils_Hook::singleton(TRUE);
  }

  /**
   * Deleting a profile field must not require Visibility, which the
   * confirmation screen does not submit. Editing still does.
   */
  public function testDeleteProfileFieldWithLegacyProfiles(): void {
    $profile = $this->createTestEntity('UFGroup', [
      'title' => 'Legacy profile',
      'name' => 'legacy_profile_field_delete',
    ], 'profile');
    $deletedField = $this->createTestEntity('UFField', [
      'uf_group_id' => $profile['id'],
      'field_name' => 'first_name',
      'field_type' => 'Individual',
      'label' => 'First Name',
      'visibility' => 'User and User Admin Only',
    ], 'delete_me');
    $updatedField = $this->createTestEntity('UFField', [
      'uf_group_id' => $profile['id'],
      'field_name' => 'last_name',
      'field_type' => 'Individual',
      'label' => 'Last Name',
      'visibility' => 'User and User Admin Only',
    ], 'update_me');

    $deleteForm = $this->getTestForm('CRM_UF_Form_Field', [
      '_qf_Field_next' => 1,
    ], [
      'action' => 'delete',
      'id' => $deletedField['id'],
    ])->processForm();

    $this->assertSame([], $deleteForm->getValidationOutput());
    $this->assertSame(0, UFField::get(FALSE)
      ->addWhere('id', '=', $deletedField['id'])
      ->execute()
      ->count());

    // Editing still builds the field through the deprecated UFField::retrieve().
    $updateForm = NULL;
    $previousHandler = set_error_handler(function (int $errno, string $errstr, string $errfile = '', int $errline = 0) use (&$previousHandler) {
      if ($errno === E_USER_DEPRECATED) {
        return TRUE;
      }
      if ($previousHandler !== NULL) {
        return (bool) $previousHandler($errno, $errstr, $errfile, $errline);
      }
      return FALSE;
    });
    try {
      $updateForm = $this->getTestForm('CRM_UF_Form_Field', [
        '_qf_Field_next' => 1,
        'field_name' => ['Individual', 'last_name'],
        'label' => 'Last Name',
        'weight' => 1,
        'group_id' => $profile['id'],
        'field_id' => $updatedField['id'],
      ], [
        'action' => 'update',
        'id' => $updatedField['id'],
      ])->processForm(FormWrapper::VALIDATED);
    }
    finally {
      restore_error_handler();
    }

    $this->assertSame(
      ['visibility' => 'Visibility is a required field.'],
      $updateForm->getValidationOutput()
    );
  }

}
