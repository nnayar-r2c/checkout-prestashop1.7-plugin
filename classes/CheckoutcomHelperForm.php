<?php


class CheckoutcomHelperForm extends HelperForm
{

    /**
     * Path location of configurations.
     *
     * @var        string
     */
    const CHECKOUTCOM_CONFIGS = CHECKOUTCOM_ROOT . DIRECTORY_SEPARATOR . 'configs';

    /**
     * Prefix for config panels variables.
     *
     * @var        string
     */
    const CHECKOUTCOM_CONFIGS_PREFIX = 'config_';

    /**
     * 'Title' field.
     *
     * @var        string
     */
    const FIELD_TITLE = 'title';

    /**
     * 'Title' field.
     *
     * @var        string
     */
    const FIELD_ICON = 'icon';

    /**
     * 'Fields' field.
     *
     * @var        string
     */
    const FIELD_FIELDS = 'fields';

    /**
     * 'Type' field.
     *
     * @var        string
     */
    const FIELD_TYPE = 'type';

    /**
     * 'Name' field.
     *
     * @var        string
     */
    const FIELD_NAME = 'name';

    /**
     * 'Default' field.
     *
     * @var        string
     */
    const FIELD_DEFAULT = 'default';

    /**
     * 'Id' field.
     *
     * @var        string
     */
    const FIELD_ID = 'id';

    /**
     * 'Value' field.
     *
     * @var        string
     */
    const FIELD_VALUE = 'value';

    /**
     * 'Values' field.
     *
     * @var        string
     */
    const FIELD_VALUES = 'values';

    /**
     * 'Label' field.
     *
     * @var        string
     */
    const FIELD_LABEL = 'label';

    /**
     * 'Is bool' field.
     *
     * @var        string
     */
    const FIELD_IS_BOOL = 'is_bool';

    /**
     * 'Desc' field.
     *
     * @var        string
     */
    const FIELD_DESC = 'desc';

    /**
     * 'Required' field.
     *
     * @var        string
     */
    const FIELD_REQUIRED = 'required';

    /**
     * 'Options' field.
     *
     * @var        string
     */
    const FIELD_OPTIONS = 'options';

    /**
     * 'Column' field.
     *
     * @var        string
     */
    const FIELD_COL = 'col';

    /**
     * List of settings.
     *
     * @var        array
     */
    protected $settings = array();

    /**
     * Setup HelperForm.
     */
    public function __construct() {

        parent::__construct();
        $this->loadSettings();

    }


    /**
     * Methods
     */

    /**
     * Loads settings.
     */
    public function loadSettings() {

        $files = scandir(static::CHECKOUTCOM_CONFIGS);

        foreach ($files as $file) {

            if(strpos($file, '.json') !== false) {
                $this->settings []= json_decode(Utilities::getFile(static::CHECKOUTCOM_CONFIGS . DIRECTORY_SEPARATOR . $file), true);
            }

        }

    }


    public function addToSmarty(&$smarty) {

        foreach ($this->settings as $s) {
            $smarty->assign(static::CHECKOUTCOM_CONFIGS_PREFIX . strtolower(str_replace(' ', '', $s[static::FIELD_TITLE])), $this->generateForm($s));
        }

    }

    /**
     * Generate HTML for form.
     *
     * @overriden
     * @param      array  $s
     * @return     string
     */
    public function generateForm($s) {


        $form = array('legend' => array(static::FIELD_TITLE => $this->l($s[static::FIELD_TITLE]),
                                        static::FIELD_ICON => Utilities::getValueFromArray($s, static::FIELD_ICON)),
                    'input' => array(),
                    'submit' => array(static::FIELD_TITLE => $this->l('Save')));

        $inputs = array();

        foreach ($s[static::FIELD_FIELDS] as $field) {
            $form['input'] []= $this->{$field[static::FIELD_TYPE]}($field);
        }

        return parent::generateForm(array(array('form' => $form)));

    }


    /**
     * Setters and Getters
     */

    /**
     * Set values for the inputs.
     *
     * @return     array  The configuration form values.
     */
    public function getConfigFormValues()
    {

        $values = array();

        foreach($this->settings as $s) {

            foreach($s[static::FIELD_FIELDS] as $field) {
                $values[$field[static::FIELD_NAME]] = Configuration::get(static::FIELD_NAME, $field[static::FIELD_DEFAULT]);
            }

        }

        return $values;

    }


    /**
     * Adpat fields
     */

    /**
     * Generate prestashop switch from configuration.
     * @param      array $field
     * @return     array
     */
    protected function switch(array &$field) {

        $options = function(array $options) {
            $arr = array();
            foreach ($options as $option) {
                $arr []= array(
                    static::FIELD_ID => $option[static::FIELD_ID],
                    static::FIELD_VALUE => $option[static::FIELD_VALUE],
                    static::FIELD_LABEL => $this->l($option[static::FIELD_LABEL])
                );
            }
            return $arr;
        };

        return array(
                static::FIELD_TYPE => $field[static::FIELD_TYPE],
                static::FIELD_LABEL => $this->l($field[static::FIELD_LABEL]),
                static::FIELD_NAME => $field[static::FIELD_NAME],
                static::FIELD_REQUIRED => Utilities::getValueFromArray($field, static::FIELD_REQUIRED, false),
                static::FIELD_IS_BOOL => Utilities::getValueFromArray($field, static::FIELD_IS_BOOL, true),
                static::FIELD_DESC => $this->l(Utilities::getValueFromArray($field, static::FIELD_DESC)),
                static::FIELD_VALUES => $options($field['options']),
            );

    }

    /**
     * Generate prestashop select from configuration.
     * @param      array $field
     * @return     array
     */
    protected function select(array &$field) {

        $select = function(array $select) {

            $options = array(
                        'query' => array(),
                        static::FIELD_ID  => 'id_option',
                        static::FIELD_NAME => static::FIELD_NAME
                    );

            foreach ($select as $s) {
                $options['query'] []= array(
                    'id_option' => $s[static::FIELD_VALUE],
                    static::FIELD_NAME => $s[static::FIELD_LABEL]
                );
            }

            return $options;

        };

        return array(
                static::FIELD_TYPE => $field[static::FIELD_TYPE],
                static::FIELD_LABEL => $this->l($field[static::FIELD_LABEL]),
                static::FIELD_NAME => $field[static::FIELD_NAME],
                static::FIELD_REQUIRED => Utilities::getValueFromArray($field, static::FIELD_REQUIRED, false),
                static::FIELD_DESC => $this->l(Utilities::getValueFromArray($field, static::FIELD_DESC)),
                'options' => $select($field['options']),
            );

    }

    /**
     * Generate prestashop text from configuration.
     * @param      array $field
     * @return     array
     */
    protected function text(array &$field) {

        return array(
                static::FIELD_COL => Utilities::getValueFromArray($field, static::FIELD_COL, 2),
                static::FIELD_TYPE => $field[static::FIELD_TYPE],
                static::FIELD_LABEL => $this->l($field[static::FIELD_LABEL]),
                static::FIELD_NAME => $field[static::FIELD_NAME],
                static::FIELD_DEFAULT => Utilities::getValueFromArray($field, static::FIELD_DEFAULT),
                static::FIELD_REQUIRED => Utilities::getValueFromArray($field, static::FIELD_REQUIRED, false),
                static::FIELD_DESC => $this->l(Utilities::getValueFromArray($field, static::FIELD_DESC)),
            );

    }

    /**
     * Generate prestashop password from configuration.
     * @param      array $field
     * @return     array
     */
    protected function password(array &$field) {

        return array(
                static::FIELD_COL => Utilities::getValueFromArray($field, static::FIELD_COL, 3),
                static::FIELD_TYPE => $field[static::FIELD_TYPE],
                static::FIELD_LABEL => $this->l($field[static::FIELD_LABEL]),
                static::FIELD_NAME => $field[static::FIELD_NAME],
                static::FIELD_REQUIRED => Utilities::getValueFromArray($field, static::FIELD_REQUIRED, false),
                static::FIELD_DESC => $this->l(Utilities::getValueFromArray($field, static::FIELD_DESC)),
            );

    }

}