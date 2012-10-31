/**
 * 
 */

(function(Backbone) {
	//Create public ZValidate object
	Backbone.ZValidate = {};
	
	//The validation class; a *partial* implementation of Zend_Filter_Input
	Backbone.ZValidate.Validator = function(rules, object) {
		if (!_.isObject(rules)) throw {message: "Expecting rules to be an Object"};
		this.setRules(rules);
		
		if (!_.isObject(object)) throw {message: "Expecting object to be an Object"};
		this.object = object;
	};
	
	/**
     * Given an error message template, fill it out any of the known values
     * @param message String
     * @param values Object
     * @returns String
     */
	var formatMessage = Backbone.ZValidate.Validator.formatMessage = function(message, values) {
		_.each(values, function(val,key) {
	   		message = message.replace('%'+key+'%', val, 'g');
		}); 
    	return message;
    };
	
	Backbone.ZValidate.Validator.prototype = {
		/**
		 * Validator builders
		 * 
		 * Each defined function builds and returns a validator, being passed any options in the first parameter:
		 * 
		 * A validator function supports up to three parameters;
		 *   value : The value to be checked for validity.
		 *   attrs : The data currently being validated; for validators that need to check multiple fields.
		 *   field : The name of the field that contains the above value.
		 * The validator function returns an {errorcode:message} map containing any number of errors, or 
		 * false/null if valid.
		 * 
		 * The validator function will be called with 'this' set to be the associated object.
		 *     
		 * e.g.
		 * myvalidator: function(options) {
		 *   var foo = options.foo;
		 *   return function(value, attrs, field) {
		 * 		if (value has error) return { errorType: "error message" };
		 *   };
		 * }
		 * 
		 * Error messages may use the %field% and %value% placeholders, as well as any named options.
		 */
		validators : {
			alnum : function() { return function(value) { 
				if (!/^[a-zA-Z0-9]*$/.test(value)) return { notAlnum: "'%value%' contains characters which are non alphabetic and not digits" };
			};},
			alpha : function() { return function(value) {
				if (!/^[a-zA-Z]*$/.test(value)) return { notAlpha: "'%value%' contains non alphabetic characters" };
			};},
			between : function(options) {
				if (_.isArray(options)) options = {min:options.shift(), max:options.shift(), inclusive:options.shift()};
				if (typeof options.inclusive == "undefined") options.inclusive = true;
				if (typeof options.min == "undefined" || typeof options.max == "undefined")
					throw {message: "Missing option. 'min' and 'max' has to be given"};
					
				return function(value) {
					if ( options.inclusive && !(value >= options.min && value <= options.max) )
						return {notBetween: formatMessage("'%value%' is not between '%min%' and '%max%', inclusively", options)};
					else if ( !options.inclusive && !(value > options.min || value < options.max) )
						return {notBetweenStrict: formatMessage("'%value%' is not strictly between '%min%' and '%max%'", options)};
				};
			},
			callback : function(options) {
				if (_.isArray(options)) options = {callback:options.shift()};
				if (_.isArray(options.callback)) options.callback = options.callback[1]; //Convert from PHP's array(class,methodname) to methodname
				if (!_.isString(options.callback)) throw {message: "Expected the callback option to be a method name"};
				
				return function(value, attrs, field) {
					var callback = this[options.callback];
					if (!_.isFunction(callback)) throw {message: "Method '"+options.callback+"' doesn't exist in the current object"};
					
					if ( callback(value, attrs, field) ) return false;
					else return {callbackInvalid: "'%value%' is not valid"};
				};
			},
			date : function() { return function(value) {
				if (_.isNaN(Date.parse(value))) return {dateInvalidDate: "'%value%' does not appear to be a valid date"};
			}; },
			digits : function() { return function(value) {
				if (!/^[0-9]*$/.test(value)) return {notDigits: "'%value%' must contain only digits"};
			}; },
			emailaddress : function() { return function(value) {
				var regex = new RegExp("[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?");
				if (!regex.test(value)) return {emailAddressInvalidFormat: "'%value%' is not a valid email address."};
			}; },
			float : function() { return function(value) {
				if (_.isNan(parseFloat(value))) return {notFloat: "'%value%' does not appear to be a float"};
			}; },
			greaterthan : function(options) { 
				if (_.isArray(options)) options = {min:options.shift()};
				if (typeof options.min == "undefined") throw {message: "Missing option. 'min' must be given."};
				
				return function(value) {
					if (!(value > options.min)) return {notGreaterThan: formatValue("'%value%' is not greater than '%min%'", options)};
				};
			},
			hex : function() { return function(value) {
				if (!/^[0-9A-Fa-f]$/.test(value)) return {notHex: "'%value%' has not only hexadecimal digit characters"};
			}; },
			identical : function(options) {
				if (_.isArray(options)) options = {token:options.shift()};
				if (typeof options.token == "undefined") throw {message: "Missing option. 'token' must be given."};
				
				return function(value, attrs) {
					if (attrs[options.token] !== value) return {notSame: "The two given tokens do not match"};
				};
			},
			inarray : function(options) {
				//Accept array form for haystack in either [a,b,c] or [[a,b,c]] form.
				if (_.isArray(options)) options = {haystack:_.isArray(_.first(options)) ? _.first(options) : options};
				if (!_.isArray(options.haystack)) throw {message: "Missing or invalid option. 'haystack' must be given, and be an array."};
				
				return function(value) {
					if (_.indexOf(options.haystack, value)==-1) return {notInArray: "'%value%' was not a valid option"};
				};
			},
			int : function() { return function(value) {
				if (_.isNan(parseInt(value))) return {notInt: "'%value%' does not appear to be an integer"};
			}; },
			lessthan : function(options) {
				if (_.isArray(options)) options = {max:options.shift()};
				if (typeof options.max == "undefined") throw {message: "Missing option. 'max' must be given."};

				return function(value) {
					if (!(value < options.max)) return {notLessThan: formatMessage("'%value%' is not less than '%max%'", options)};
				};
			},
			regex : function(options) {
				if (_.isArray(options)) options = {pattern:options.shift()};
				if (!_.isString(options.pattern)) throw {message: "Expected pattern to be a string"}; 
				//PHP stores regexes as strings with surrounding delimiters. The regexp constructor expects an un-delimited string, so remove them.
				var pattern = new RegExp( options.pattern.substr(1,options.pattern.length-2) );
				
				return function(value) {
					if (!pattern.test(value)) return {regexNotMatch: formatMessage("'%value%' does not match against pattern '%pattern%'", options)};	
				};
			},
			stringlength : function(options) {
				if (_.isArray(options)) options = {min:options.shift(), max:options.shift()};
				if (typeof options.min == "undefined") options.min = 0;
				
				return function(value) {
					if (value.length < options.min) return {stringLengthTooShort: formatMessage("'%value%' is less than %min% characters long", options)};
					if (options.max && value.length > options.max) return {stringLengthTooLong: formatMessage("'%value%' is more than %max% characters long", options)};
				};
			}
		},
			
		//'Constants'
	    ALLOW_EMPTY	: 'allowEmpty',
	    BREAK_CHAIN	: 'breakChainOnFailure',
	    PRESENCE : 'presence',
	    MISSING_MESSAGE : 'missingMessage',
	    NOT_EMPTY_MESSAGE : 'notEmptyMessage',
	    METACOMMAND_DEFAULTS : {
            allowEmpty : false,
            breakChainOnFailure : false, 
            presence : 'optional',
        	missingMessage : "Field '%field%' is required, but the field is missing",
        	notEmptyMessage : "You must give a non-empty value for field '%field%'"
	    },
	    UNSUPPORTED_METACOMMANDS : ['default', 'fields', 'messages'],

	    //RULE_WILDCARD : '*', TODO: NOT SUPPORTED YET
	    PRESENCE_OPTIONAL : 'optional',
	    PRESENCE_REQUIRED : 'required',
	    	    
	    //Map of normalised rules for this validator
	    rules : {},
	    
	    //Data for this validator to check
	    data : {},
	    
	    //Object; typically the model instance
	    object : {},
	    
	    //Cache isValid results
	    _isValid : undefined,
	    
	    //Error messages, split by type
	    missing : {},
	    invalid : {},
	    unknown : {},
	    
	    /**
	     * Reset the validator
	     * Clears any error messages.
	     * Clears the cached _isValid result.
	     */
	    reset : function() {
	    	this._isValid = undefined;
	    	this.missing = {};
	    	this.invalid = {};
	    	this.unknown = {};
	    },
	    
	    /**
	     * Set the validator object's rules
	     * Will parse each of the rules and into a normalised object form.
	     * Will throw errors if any of the rule definitions are invalid.
	     * Resets any cached _isValid result
	     * @param rules
	     */
	    setRules : function(rules) {
	    	this.reset();
	    	this.rules = {};
	    	
		    _.each(rules, function(raw_rule, field) {
		    	//Build a default rule
		    	var rule = _.extend({}, this.METACOMMAND_DEFAULTS);
		    	rule.validators = [];
		    	
		    	//What form of rule is defined?
		    	//Validator chain:
		    	if (_.isObject(raw_rule) && _.size(raw_rule) > 0) {
		    		//Process each element in the chain
			    	_.each(raw_rule, function(val, key) {
			    		//Metacommand?
			    		if (_.has(this.METACOMMAND_DEFAULTS, key))
			    			rule[key] = val;
			    		else if (_.indexOf(this.UNSUPPORTED_METACOMMANDS, key)!==-1)
			    			throw {message: "Rule "+field+" contains unsupported metacommand "+key};
			    		//Validator:
			    		else if (!isNaN(parseInt(key))) { //numeric key
			    			rule.validators.push( this._processValidator(val) );
			    		}
			    	}, this);
		    	}
		    	//Blank validator
		    	else if (_.isArray(raw_rule) && _.size(raw_rule) == 0) {
		    	}
		    	//Single validator
		    	else {
		    		rule.validators.push( this._processValidator(raw_rule) );
		    	}
		    	
		    	this.rules[field] = rule;
		    }, this);

	    },
	    
	    /**
	     * Given an open validator definition, like "alnum", or ["stringlength", 3, 5]
	     * check it for validity, and parse it into a standard validator format. 
	     * @param validator
	     * @return object A standard validator definition;
	     */
	    _processValidator : function(validator) {
	    	if (_.isString(validator)) 
	    		validator = [validator];
	    	else if (!_.isArray(validator))
	    		throw {message: "Unexpected validator type: " + typeof validator};

    		var name = validator.shift().toLowerCase();
    		var builder = this.validators[name];
    		var options = (_.size(validator)==1 && _.isObject(_.first(validator))) ? _.first(validator) : validator;
    		if (!_.isFunction(builder))
    			throw {message: "Validation type "+name+" is invalid or not yet supported."};
    			
    		//Build the validator
    		var validator = builder(options);
    		
    		if (!_.isFunction(validator))
    			throw {message: "Validator "+name+" was not built correctly."};
    		
    		return validator;
	    },

	    /**
	     * Load a new data set into the validator.
	     * Will reset any existing errors
	     * @param data
	     */
	    setData : function(data) {
	    	this.reset();
	    	this.data = data;
	    },
	    
	    /**
	     * Validate the data against the configured rules
	     * Will cache the result so multiple calls are short-circuited.
	     * 
	     * 
	     * @returns true if there are no missing or invalid elements. 
	     */
	    isValid : function() {
	    	//Allow it to be called as isValid(data)
	    	if (arguments.length > 0)
	    		this.setData(arguments[0]);
	    	
	    	//Short-circuit return if it's been called twice.
	    	if (this._isValid !== undefined) return this._isValid;
	    	
	    	//Are there any unknown fields? [data given, with no corresponding rule]
	    	this.unknown = _.pick(this.data, _.difference( _.keys(this.data), _.keys(this.rules) ));
	    		    	
	    	//Check each rule
	    	_.each(this.rules, function(rule, field) {
	    		var value = this.data[field];

	    		//Is the field missing?
	    		if (!_.has(this.data, field)) {
	    			if (rule.presence == 'required') 
	    				this.missing[field] = { required : formatMessage(rule.missingMessage,{field:field}) };
	    			return;
	    		}
	    		
	    		//Is the field present, but empty?
	    		if (!value || _.isEmpty(value)) {
	    			if (!rule.allowEmpty) 
	    				this.invalid[field] = { isEmpty : formatMessage(rule.notEmptyMessage, {field:field}) };
	    			return;
	    		}
	    			
	    		//Otherwise, check each of the rule's validators against that value
	    		//(If breakChainOnFailure is set, abort after the first failure)
	    		else _.every(rule.validators, function(validator) {
	    			errors = validator.call(this.object, value, this.data, field); 
	    					
	    			//If any errors occur, format them and add them to the invalid attribute
	    			if (errors) {
	    				if (!this.invalid[field]) this.invalid[field]={};
	    				_.each(errors, function(message, code) {
	    					this.invalid[field][code] = formatMessage(message, {field:field, value:value});
	    				}, this);
	    			}
	    			//Should the next validator be called? depends on breakChain
	    			return !errors || !validator.breakChainOnFailure;
	    		}, this);
	    	}, this);
	    	
	    	//Was the data all valid?
	    	return this._isValid = (_.isEmpty(this.invalid) && _.isEmpty(this.missing));
	    },
	    
	    /**
	     * Fetch all error messages; invalid and missing fields
	     * @returns
	     */
	    getMessages : function() { 
	    	return _.extend({}, this.invalid, this.missing);
	    }
   };
	
	/**
	 * The ZValidate validation function;
	 * Validates the input according to the configured validators.
	 * @param attrs
	 * @param options
	 * @return false if no errors occurred, a map of errors per field if errors occurred.
	 */
	var validate = function(attrs, options) {
		if (this._zvalidator.isValid(attrs)) 
			return false;
		
		var errors = this._zvalidator.getMessages();
		
		if (options && options.partial) {
			var haystack = _.isObject(options.partial) ? _.keys(options.partial) : _.keys(attrs);
			errors = _.pick(errors, haystack);
			return _.isEmpty(errors) ? false : errors;
		} else {
			return errors;
		}
	};

	/**
	 * Extend the _validate function; needs to capture the fields being set
	 * to support partial validation. 
	 */
	var _validate = function(attrs, options) {
		//If using partial validation, remember the fields being set  
		if (options.partial) options.partial = attrs;
		
		return Backbone.Model.prototype._validate.apply(this, arguments);
	};
	
	var original = Backbone.Model;
	
	//Constructor for the ZValidate version of the model
	Backbone.ZValidate.Model = Backbone.Model.extend({
		constructor : function() {
			//Only install to a model where the .validate attribute is a list of rules (presumably)
			if (this.validate && typeof this.validate === "object") {
				//Replace the validate list with the validate function.
				this.constructor.prototype._zvalidator = new Backbone.ZValidate.Validator(this.validate, this);
				this.constructor.prototype.validate = validate;  
				this.constructor.prototype._validate = _validate;
			}
			
			original.apply(this, arguments);
		}
	});
	
	//By default, override the backbone model with the ZValidate model.
	Backbone.Model = Backbone.ZValidate.Model;

	//Provide a noConflict if preferred.
	Backbone.ZValidate.Model.noConflict = function() {
	  Backbone.Model = original;
	};
	
})(Backbone);