<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_users
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.keepalive');
HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('bootstrap.tooltip');

// Load user_profile plugin language
$lang = Factory::getLanguage();
$lang->load('plg_user_profile', JPATH_ADMINISTRATOR);

HTMLHelper::_('script', 'com_users/two-factor-switcher.min.js', array('version' => 'auto', 'relative' => true));
?>
<div class="com-users-profile__edit profile-edit">
	<?php if ($this->params->get('show_page_heading')) : ?>
		<div class="page-header">
			<h1>
				<?php echo $this->escape($this->params->get('page_heading')); ?>
			</h1>
		</div>
	<?php endif; ?>

	<form id="member-profile" action="<?php echo Route::_('index.php?option=com_users'); ?>" method="post" class="com-users-profile__edit-form form-validate form-horizontal well" enctype="multipart/form-data">
		<?php // Iterate through the form fieldsets and display each one. ?>
		<?php foreach ($this->form->getFieldsets() as $group => $fieldset) : ?>
			<?php $fields = $this->form->getFieldset($group); ?>
			<?php if (count($fields)) : ?>
				<fieldset>
					<?php // If the fieldset has a label set, display it as the legend. ?>
					<?php if (isset($fieldset->label)) : ?>
						<legend>
							<?php echo Text::_($fieldset->label); ?>
						</legend>
					<?php endif; ?>
					<?php if (isset($fieldset->description) && trim($fieldset->description)) : ?>
						<p>
							<?php echo $this->escape(Text::_($fieldset->description)); ?>
						</p>
					<?php endif; ?>
					<?php // Iterate through the fields in the set and display them. ?>
					<?php foreach ($fields as $field) : ?>
					<?php // If the field is hidden, just display the input. ?>
						<?php if ($field->hidden) : ?>
							<?php echo $field->input; ?>
						<?php else : ?>
							<div class="control-group">
								<div class="control-label">
									<?php echo $field->label; ?>
									<?php if (!$field->required && $field->type !== 'Spacer') : ?>
										<span class="optional">
											<?php echo Text::_('COM_USERS_OPTIONAL'); ?>
										</span>
									<?php endif; ?>
								</div>
								<div class="controls">
									<?php echo $field->input; ?>
								</div>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php if (count($this->twofactormethods) > 1) : ?>
			<fieldset class="com-users-profile__twofactor">
				<legend><?php echo Text::_('COM_USERS_PROFILE_TWO_FACTOR_AUTH'); ?></legend>

				<div class="com-users-profile__twofactor-method control-group">
					<div class="control-label">
						<label id="jform_twofactor_method-lbl" for="jform_twofactor_method" class="hasTooltip"
							   title="<?php echo '<strong>' . Text::_('COM_USERS_PROFILE_TWOFACTOR_LABEL') . '</strong><br>' . Text::_('COM_USERS_PROFILE_TWOFACTOR_DESC'); ?>">
							<?php echo Text::_('COM_USERS_PROFILE_TWOFACTOR_LABEL'); ?>
						</label>
					</div>
					<div class="controls">
						<?php echo HTMLHelper::_('select.genericlist', $this->twofactormethods, 'jform[twofactor][method]', array('onchange' => 'Joomla.twoFactorMethodChange()'), 'value', 'text', $this->otpConfig->method, 'jform_twofactor_method', false); ?>
					</div>
				</div>
				<div id="com_users_twofactor_forms_container" class="com-users-profile__twofactor-form">
					<?php foreach ($this->twofactorform as $form) : ?>
						<?php $hide = $form['method'] == $this->otpConfig->method ? '' : ' class="hidden"'; ?>
						<div id="com_users_twofactor_<?php echo $form['method'] . '"' . $hide; ?>>
							<?php echo $form['form']; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<fieldset class="com-users-profile__oteps">
				<legend>
					<?php echo Text::_('COM_USERS_PROFILE_OTEPS'); ?>
				</legend>
				<div class="alert alert-info">
					<span class="fa fa-info-circle" aria-hidden="true"></span><span class="sr-only"><?php echo Text::_('INFO'); ?></span>
					<?php echo Text::_('COM_USERS_PROFILE_OTEPS_DESC'); ?>
				</div>
				<?php if (empty($this->otpConfig->otep)) : ?>
					<div class="alert alert-warning">
						<span class="fa fa-exclamation-circle" aria-hidden="true"></span><span class="sr-only"><?php echo Text::_('WARNING'); ?></span>
						<?php echo Text::_('COM_USERS_PROFILE_OTEPS_WAIT_DESC'); ?>
					</div>
				<?php else : ?>
					<?php foreach ($this->otpConfig->otep as $otep) : ?>
						<span class="col-md-3">
							<?php echo substr($otep, 0, 4); ?>-<?php echo substr($otep, 4, 4); ?>-<?php echo substr($otep, 8, 4); ?>-<?php echo substr($otep, 12, 4); ?>
						</span>
					<?php endforeach; ?>
					<div class="clearfix"></div>
				<?php endif; ?>
			</fieldset>
		<?php endif; ?>

		<div class="com-users-profile__edit-submit control-group">
			<div class="controls">
				<button type="submit" class="btn btn-primary validate" name="task" value="profile.save">
					<?php echo Text::_('JSUBMIT'); ?>
				</button>
				<button type="submit" class="btn btn-danger" name="task" value="profile.cancel" formnovalidate>
					<?php echo Text::_('JCANCEL'); ?>
				</button>
				<input type="hidden" name="option" value="com_users">
			</div>
		</div>
		<?php echo HTMLHelper::_('form.token'); ?>
	</form>
</div>
