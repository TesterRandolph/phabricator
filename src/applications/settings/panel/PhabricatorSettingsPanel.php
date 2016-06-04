<?php

/**
 * Defines a settings panel. Settings panels appear in the Settings application,
 * and behave like lightweight controllers -- generally, they render some sort
 * of form with options in it, and then update preferences when the user
 * submits the form. By extending this class, you can add new settings
 * panels.
 *
 * @task config   Panel Configuration
 * @task panel    Panel Implementation
 * @task internal Internals
 */
abstract class PhabricatorSettingsPanel extends Phobject {

  private $user;
  private $viewer;
  private $controller;
  private $navigation;
  private $overrideURI;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setOverrideURI($override_uri) {
    $this->overrideURI = $override_uri;
    return $this;
  }

  final public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    return $this;
  }

  final public function getController() {
    return $this->controller;
  }

  final public function setNavigation(AphrontSideNavFilterView $navigation) {
    $this->navigation = $navigation;
    return $this;
  }

  final public function getNavigation() {
    return $this->navigation;
  }

  final public static function getAllPanels() {
    $panels = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getPanelKey')
      ->execute();
    return msortv($panels, 'getPanelOrderVector');
  }

  final public static function getAllDisplayPanels() {
    $panels = array();
    $groups = PhabricatorSettingsPanelGroup::getAllPanelGroupsWithPanels();
    foreach ($groups as $group) {
      foreach ($group->getPanels() as $key => $panel) {
        $panels[$key] = $panel;
      }
    }

    return $panels;
  }

  final public function getPanelGroup() {
    $group_key = $this->getPanelGroupKey();

    $groups = PhabricatorSettingsPanelGroup::getAllPanelGroupsWithPanels();
    $group = idx($groups, $group_key);
    if (!$group) {
      throw new Exception(
        pht(
          'No settings panel group with key "%s" exists!',
          $group_key));
    }

    return $group;
  }


/* -(  Panel Configuration  )------------------------------------------------ */


  /**
   * Return a unique string used in the URI to identify this panel, like
   * "example".
   *
   * @return string Unique panel identifier (used in URIs).
   * @task config
   */
  public function getPanelKey() {
    return $this->getPhobjectClassConstant('PANELKEY');
  }


  /**
   * Return a human-readable description of the panel's contents, like
   * "Example Settings".
   *
   * @return string Human-readable panel name.
   * @task config
   */
  abstract public function getPanelName();


  /**
   * Return a panel group key constant for this panel.
   *
   * @return const Panel group key.
   * @task config
   */
  abstract public function getPanelGroupKey();


  /**
   * Return false to prevent this panel from being displayed or used. You can
   * do, e.g., configuration checks here, to determine if the feature your
   * panel controls is unavailble in this install. By default, all panels are
   * enabled.
   *
   * @return bool True if the panel should be shown.
   * @task config
   */
  public function isEnabled() {
    return true;
  }


  /**
   * Return true if this panel is available to administrators while editing
   * system agent accounts.
   *
   * @return bool True to enable edit by administrators.
   * @task config
   */
  public function isEditableByAdministrators() {
    return false;
  }


/* -(  Panel Implementation  )----------------------------------------------- */


  /**
   * Process a user request for this settings panel. Implement this method like
   * a lightweight controller. If you return an @{class:AphrontResponse}, the
   * response will be used in whole. If you return anything else, it will be
   * treated as a view and composed into a normal settings page.
   *
   * Generally, render your settings panel by returning a form, then return
   * a redirect when the user saves settings.
   *
   * @param   AphrontRequest  Incoming request.
   * @return  wild            Response to request, either as an
   *                          @{class:AphrontResponse} or something which can
   *                          be composed into a @{class:AphrontView}.
   * @task panel
   */
  abstract public function processRequest(AphrontRequest $request);


  /**
   * Get the URI for this panel.
   *
   * @param string? Optional path to append.
   * @return string Relative URI for the panel.
   * @task panel
   */
  final public function getPanelURI($path = '') {
    $path = ltrim($path, '/');

    if ($this->overrideURI) {
      return rtrim($this->overrideURI, '/').'/'.$path;
    }

    $key = $this->getPanelKey();
    $key = phutil_escape_uri($key);

    if ($this->getUser()->getPHID() != $this->getViewer()->getPHID()) {
      $user_id = $this->getUser()->getID();
      return "/settings/{$user_id}/panel/{$key}/{$path}";
    } else {
      return "/settings/panel/{$key}/{$path}";
    }
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Generates a key to sort the list of panels.
   *
   * @return string Sortable key.
   * @task internal
   */
  final public function getPanelOrderVector() {
    return id(new PhutilSortVector())
      ->addString($this->getPanelName());
  }

  protected function loadTargetPreferences() {
    $viewer = $this->getViewer();
    $user = $this->getUser();

    $preferences = PhabricatorUserPreferences::loadUserPreferences($user);

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $preferences,
      PhabricatorPolicyCapability::CAN_EDIT);

    return $preferences;
  }

  protected function newDialog() {
    return $this->getController()->newDialog();
  }

  protected function writeSetting(
    PhabricatorUserPreferences $preferences,
    $key,
    $value) {
    $viewer = $this->getViewer();
    $request = $this->getController()->getRequest();

    $editor = id(new PhabricatorUserPreferencesEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $xactions = array();
    $xactions[] = $preferences->newTransaction($key, $value);
    $editor->applyTransactions($preferences, $xactions);
  }

}
