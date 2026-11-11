<?php

use Civi\Api4\UFField;
use Civi\Test\FormWrapper;

/**
 * Test CRM_UF_Form_Field.
 *
 * @group headless
 */
class CRM_UF_Form_FieldTest extends CiviUnitTestCase {

  /**
   * Status of legacyprofiles before this test enabled it.
   *
   * @var string|null
   */
  private ?string $legacyProfilesStatus = NULL;

  /**
   * @throws \CRM_Extension_Exception
   */
  protected function setUp(): void {
    parent::setUp();
    $manager = CRM_Extension_System::singleton()->getManager();
    $this->legacyProfilesStatus = $manager->getStatus('legacyprofiles');
    if ($this->legacyProfilesStatus !== CRM_Extension_Manager::STATUS_INSTALLED) {
      $manager->install('legacyprofiles');
    }
    // The unit-test hook caches its module list. Replace it so a newly
    // enabled extension is included.
    CRM_Utils_Hook::singleton(TRUE);
    $this->hookClass = CRM_Utils_Hook::singleton();
  }

  /**
   * @throws \CRM_Extension_Exception
   */
  protected function tearDown(): void {
    $this->restoreLegacyProfilesStatus();
    CRM_Utils_Hook::singleton(TRUE);
    $this->hookClass = CRM_Utils_Hook::singleton();
    parent::tearDown();
  }

  /**
   * Deleting a profile field must not require Visibility, which the
   * confirmation screen does not submit. Editing still does.
   *
   * @throws \CRM_Core_Exception
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
    $this->captureErrors(E_USER_DEPRECATED, function () use ($profile, $updatedField, &$updateForm) {
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
    });

    $this->assertSame(
      ['visibility' => 'Visibility is a required field.'],
      $updateForm->getValidationOutput()
    );
  }

  /**
   * Put legacyprofiles back to the status it had before setUp().
   *
   * @throws \CRM_Extension_Exception
   */
  private function restoreLegacyProfilesStatus(): void {
    if ($this->legacyProfilesStatus === NULL
      || $this->legacyProfilesStatus === CRM_Extension_Manager::STATUS_INSTALLED
    ) {
      return;
    }
    $manager = CRM_Extension_System::singleton()->getManager();
    $status = $manager->getStatus('legacyprofiles');
    if ($status === CRM_Extension_Manager::STATUS_INSTALLED) {
      $manager->disable('legacyprofiles');
      $status = CRM_Extension_Manager::STATUS_DISABLED;
    }
    if ($this->legacyProfilesStatus !== CRM_Extension_Manager::STATUS_DISABLED
      && $status === CRM_Extension_Manager::STATUS_DISABLED
    ) {
      $manager->uninstall('legacyprofiles');
    }
  }

}
