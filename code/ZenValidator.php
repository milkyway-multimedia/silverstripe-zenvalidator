<?php
/**
 * 
 * @package ZenValidator
 * @license BSD License http://www.silverstripe.org/bsd-license
 * @author <shea@silverstripe.com.au>
 *
 **/
class ZenValidator extends Validator{


	private static $default_js = true;

	/**
	 * constraints assigned to this validator
	 * @var array
	 **/
	protected $constraints = array();


	/**
	 * @var Boolean
	 **/
	protected $parsleyEnabled;


	/**
	 * @var Boolean
	 **/
	protected $defaultJS;


	/**
	 * @param boolean $parsleyEnabled
	 **/
	public function __construct($constraints = array(), $parsleyEnabled = true, $defaultJS = null){
		parent::__construct();
		
		$this->parsleyEnabled = $parsleyEnabled;
		$this->defaultJS = ($defaultJS !== null) ? $defaultJS : $this->config()->get('default_js');

		if(count($constraints)){
			$this->setConstraints($constraints);
		}
	}


	/**
	 * @param Form $form
	 */
	public function setForm($form) {
		parent::setForm($form);
		
		// a bit of a hack, need a security token to be set on form so that we can iterate over $form->Fields()
		if(!$form->getSecurityToken()) $form->disableSecurityToken();

		// disable parsley in the cms
		if(is_subclass_of($form->getController()->class, 'LeftAndMain')){
			$this->parsleyEnabled = false;
		}

		// set the field on all constraints
		foreach ($this->constraints as $fieldName => $constraints) {
			foreach ($constraints as $constraint) {
				$constraint->setField($this->form->Fields()->dataFieldByName($fieldName));
			}
		}

		// apply parsley
		if($this->parsleyEnabled) $this->applyParsley();

		return $this;
	}


	/**
	 * applyParsley
	 * @return this
	 **/
	public function applyParsley(){
		$this->parsleyEnabled = true;
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR.'/jquery-entwine/dist/jquery.entwine-dist.js');
		Requirements::javascript(ZENVALIDATOR_PATH . '/javascript/parsley/parsley.remote.min.js');
		Requirements::javascript(ZENVALIDATOR_PATH.'/javascript/zenvalidator.js');
		
		$lang = i18n::get_lang_from_locale(i18n::get_locale());
		if($lang != 'en') {
			Requirements::javascript(ZENVALIDATOR_PATH . '/javascript/parsley/i18n/' . $lang . '.js');
		}
		
		if($this->form){
			if ($this->defaultJS) {
				$this->form->addExtraClass('parsley');
			}else{
				$this->form->addExtraClass('custom-parsley');
			}

			foreach ($this->constraints as $fieldName => $constraints) {
				foreach ($constraints as $constraint) {
					$constraint->applyParsley();
				}
			}
		}

		return $this;
	}


	/**
	 * disableParsley
	 * @return this
	 **/
	public function disableParsley(){
		$this->parsleyEnabled = false;
		if($this->form){
			$this->form->removeExtraClass('parsley');	
			$this->form->removeExtraClass('custom-parsley');	
		}
		return $this;
	}


	/**
	 * parsleyIsEnabled
	 * @return boolean
	 **/
	public function parsleyIsEnabled(){
		return $this->parsleyEnabled;
	}


	/**
	 * setConstraint - sets a ZenValidatorContraint on this validator
	 * @param String $field - name of the field to be validated
	 * @param ZenFieldValidator $constraint 
	 * @return $this
	 **/
	public function setConstraint($fieldName, $constraint){
		// remove existing constraint if it already exists
		if($this->getConstraint($fieldName, $constraint->class)){
			$this->removeConstraint($fieldName, $constraint->class);
		}

		$this->constraints[$fieldName][$constraint->class] = $constraint;

		if($this->form){
			$field = $constraint->setField($this->form->Fields()->dataFieldByName($fieldName));
			if($this->parsleyEnabled){
				$field->applyParsley();
			}
		}

		return $this;
	}


	/**
	 * setConstraints - sets multiple constraints on this validator
	 * @param array $constraints - $fieldName => ZenValidatorConstraint
	 * @return $this
	 **/
	public function setConstraints($constraints){
		foreach ($constraints as $fieldName => $v) {
			if (is_array($v)) {
				foreach ($v as $constraintFromArray) {
					$this->setConstraint($fieldName, $constraintFromArray);
				}
			}else{
				$this->setConstraint($fieldName, $v);
			}
		}
		return $this;
	}	


	/**
	 * get a constraint by fieldName, constraintName
	 * @param String $fieldName
	 * @param String $constraintName
	 * @return ZenValidatorConstraint
	 **/
	public function getConstraint($fieldName, $constraintName){
		if(isset($this->constraints[$fieldName][$constraintName])){
			return $this->constraints[$fieldName][$constraintName];
		}
	}


	/**
	 * get constraints by fieldName
	 * @param String $fieldName
	 * @return array
	 **/
	public function getConstraints($fieldName){
		if(isset($this->constraints[$fieldName])){
			return $this->constraints[$fieldName];
		}
	}


	/**
	 * remove a constraint from a field
	 * @param String $field - name of the field to have a constraint removed from
	 * @param String $constraintName - class name of constraint
	 * @return $this
	 **/
	function removeConstraint($fieldName, $constraintName){
		if($constraint = $this->getConstraint($fieldName, $constraintName)){
			if($this->form) $constraint->removeParsley();
			unset($this->constraints[$fieldName][$constraint->class]);
			unset($constraint);
			
		}
		return $this;
	}


	/**
	 * remove all constraints from a field
	 * @param String $field - name of the field to have constraints removed from
	 * @return $this
	 **/
	function removeConstraints($fieldName){
		if($constraints = $this->getConstraints($fieldName)){
			foreach ($constraints as $k => $v) {
				$this->removeConstraint($fieldName, $k);
			}
			unset($this->constraints[$fieldName]);
		}
		return $this;
	}


	/**
	 * A quick way of adding required constraints to a number of fields
	 * @param array $fieldNames - can be either indexed array of fieldnames, or associative array of fieldname => message
	 * @return this
	 */
	public function addRequiredFields($fields){
		if(ArrayLib::is_associative($fields)){
			foreach ($fields as $k => $v) {
				$constraint = Constraint_required::create();
				if($v) $constraint->setMessage($v);
				$this->setConstraint($k, $constraint);
			}	
		}else{
			foreach ($fields as $field) {
				$this->setConstraint($field, Constraint_required::create());
			}
		}

		return $this;
	}


	/**
	 * Performs the php validation on all ZenValidatorConstraints attached to this validator
	 * @return boolean
	 **/
	public function php($data){
		$valid = true;

        // If we want to ignore validation
        if(get_class($this->form->buttonClicked()) === 'FormActionNoValidation') {
            return $valid;
        }

        // validate against form field validators
    	$fields = $this->form->Fields();
	    foreach($fields as $field) {
	        $valid = ($field->validate($this) && $valid);
	    }

	    // validate against ZenValidator constraints
		foreach ($this->constraints as $fieldName => $constraints) {
				if($this->form->Fields()->dataFieldByName($fieldName)->validationApplies()){
					foreach ($constraints as $constraint) {
						if(!$constraint->validate($data[$fieldName])){
							$this->validationError($fieldName, $constraint->getMessage(), 'required');
							$valid = false;
						}
					}
				}
		}
		return $valid;
	}


	/**
	 * This method is not imeplemented because form->transform calls it, but not all FormTransformations
	 * necessarily want to remove validation... right?
	 * Use removeAllValidation() instead.
	 **/
	public function removeValidation(){

	}


	/**
	 * Removes all constraints from this validator.
	 **/
	public function removeAllValidation(){
		if($this->form){
			foreach ($this->constraints as $fieldName => $constraints) {
				foreach ($constraints as $constraint) {
					$constraint->removeParsley();
					unset($constraint);
				}
			}	
		}
		$this->constraints = array();
	}
}
