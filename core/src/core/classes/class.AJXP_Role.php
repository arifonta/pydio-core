<?php
/*
 * Copyright 2007-2012 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

defined('AJXP_EXEC') or die('Access not allowed');

const AJXP_VALUE_CLEAR = "AJXP_VALUE_CLEAR";
const AJXP_REPO_SCOPE_ALL = "AJXP_REPO_SCOPE_ALL";

class AJXP_Role implements AjxpGroupPathProvider
{

    protected $groupPath;
    protected $roleId;
    protected $roleLabel;

    protected $acls = array();
    protected $parameters = array();
    protected $actions = array();
    protected $autoApplies = array();


    public function isGroupRole(){
        return strpos($this->roleId, "AJXP_GRP_") === 0;
    }
    public function isUserRole(){
        return strpos($this->roleId, "AJXP_USER_") === 0;
    }

    /**
     * @param string $repositoryId
     * @param string $rightString
     * @return void
     */
    public function setAcl($repositoryId, $rightString){
        if(empty($rightString)){
            if(isSet($this->acls[$repositoryId])) unset($this->acls[$repositoryId]);
        }else{
            $this->acls[$repositoryId] = $rightString;
        }
        return;
    }
    /**
     * @param string $repositoryId
     * @return string
     */
    public function getAcl($repositoryId){
        if(isSet($this->acls[$repositoryId])) {
            return $this->acls[$repositoryId];
        }
        return "";
    }
    /**
     * @return array Associative array[REPO_ID] => RIGHT_STRING (r / w / rw / AJXP_VALUE_CLEAR)
     */
    public function listAcls(){
        return $this->acls;
    }

    /**
     * @param string $pluginId
     * @param string $parameterName
     * @param mixed $parameterValue can be AJXP_VALUE_CLEAR (force clear previous), or empty string for clearing value (apply previous).
     * @param string|null $repositoryId
     */
    public function setParameterValue($pluginId, $parameterName, $parameterValue, $repositoryId = null){
        if($repositoryId == null) $repositoryId = AJXP_REPO_SCOPE_ALL;
        if(empty($parameterValue)){
            if(isSet($this->parameters[$repositoryId][$pluginId][$parameterName])){
                unset($this->parameters[$repositoryId][$pluginId][$parameterName]);
                if(!count($this->parameters[$repositoryId][$pluginId])) unset($this->parameters[$repositoryId][$pluginId]);
                if(!count($this->parameters[$repositoryId])) unset($this->parameters[$repositoryId]);
            }
        }else{
            $this->parameters = $this->setArrayValue($this->parameters, $repositoryId, $pluginId, $parameterName, $parameterValue);
        }
        return;
    }

    /**
     * @param string $pluginId
     * @param string $parameterName
     * @param string $repositoryId
     * @param mixed $parameterValue
     * @return mixed
     */
    public function filterParameterValue($pluginId, $parameterName, $repositoryId, $parameterValue){
        if(isSet($this->parameters[AJXP_REPO_SCOPE_ALL][$pluginId][$parameterName])){
            $v = $this->parameters[AJXP_REPO_SCOPE_ALL][$pluginId][$parameterName];
            if($v == AJXP_VALUE_CLEAR) return "";
            else return $v;
        }
        if(isSet($this->parameters[$repositoryId][$pluginId][$parameterName])){
            $v = $this->parameters[$repositoryId][$pluginId][$parameterName];
            if($v == AJXP_VALUE_CLEAR) return "";
            else return $v;
        }
        return $parameterValue;
    }
    /**
     * @return array Associative array of parameters : array[REPO_ID][PLUGIN_ID][PARAMETER_NAME] = PARAMETER_VALUE
     */
    public function listParameters(){
        return $this->parameters;
    }

    /**
     * @param string $pluginId
     * @param string $actionName
     * @param string|null $repositoryId
     * @param string $state
     */
    public function setActionState($pluginId, $actionName, $repositoryId = null, $state = "disabled"){
        $this->actions = $this->setArrayValue($this->actions, $repositoryId, $pluginId, $actionName, $state);
        return;
    }
    /**
     * @param string $pluginId
     * @param string $actionName
     * @param string $repositoryId
     * @param boolean $inputState
     * @return boolean
     */
    public function actionEnabled($pluginId, $actionName, $repositoryId, $inputState){
        if(isSet($this->actions[AJXP_REPO_SCOPE_ALL][$pluginId][$actionName])){
            return $this->actions[AJXP_REPO_SCOPE_ALL][$pluginId][$actionName] == "enabled" ? true : false ;
        }
        if(isSet($this->actions[$repositoryId][$pluginId][$actionName])){
            return $this->actions[$repositoryId][$pluginId][$actionName]  == "enabled" ? true : false ;
        }
        return $inputState;
    }
    /**
     * @return array
     */
    public function listActionStates(){
        return $this->actions;
    }

    /**
     * @param AJXP_Role $role
     * @return AJXP_Role
     */
    public function override(AJXP_Role $role){
        $newRole = new AJXP_Role();

        $newAcls = array_merge($role->listAcls(), $this->listAcls());
        foreach($newAcls as $repoId => $rightString){
            if($rightString == AJXP_VALUE_CLEAR) continue;
            $newRole->setAcl($repoId, $rightString);
        }

        $newParams = $this->array_merge_recursive2($role->listParameters(), $this->listParameters());
        foreach($newParams as $repoId => $data){
            foreach ($data as $pluginId => $param) {
                foreach($param as $parameterName => $parameterValue){
                    if($parameterValue == AJXP_VALUE_CLEAR) continue;
                    $newRole->setParameterValue($pluginId, $parameterName, $parameterValue, $repoId);
                }
            }
        }

        $newActions = $this->array_merge_recursive2($role->listActionStates(), $this->listActionStates());
        foreach($newActions as $repoId => $data){
            foreach ($data as $pluginId => $action) {
                foreach($action as $actionName => $actionState){
                    $newRole->setActionState($pluginId, $actionName, $repoId, $actionState);
                }
            }
        }

        return $newRole;
    }

    /**
     * @param array
     * @param key1
     * @param key2
     * @param key3...
     * @param value
     */
    function setArrayValue(){
        $args = func_get_args();
        $arr = array_shift($args);
        $value = array_pop($args);
        $current = &$arr;
        foreach ($args as $index => $key){
            if($index < count($args)-1) {
                if(!is_array($current[$key])) $current[$key] = array();
                $current = &$current[$key];
            }else{
                $current[$key] = $value;
            }
        }
        return $arr;
    }

    function array_merge_recursive2($array1, $array2)
    {
        $arrays = func_get_args();
        $narrays = count($arrays);

        // check arguments
        // comment out if more performance is necessary (in this case the foreach loop will trigger a warning if the argument is not an array)
        for ($i = 0; $i < $narrays; $i ++) {
            if (!is_array($arrays[$i])) {
                // also array_merge_recursive returns nothing in this case
                trigger_error('Argument #' . ($i+1) . ' is not an array - trying to merge array with scalar! Returning null!', E_USER_WARNING);
                return;
            }
        }

        // the first array is in the output set in every case
        $ret = $arrays[0];

        // merege $ret with the remaining arrays
        for ($i = 1; $i < $narrays; $i ++) {
            foreach ($arrays[$i] as $key => $value) {
                            //if (((string) $key) === ((string) intval($key))) { // integer or string as integer key - append
                //    $ret[] = $value;
                //}
                //{ // string key - megre
                    if (is_array($value) && isset($ret[$key])) {
                        // if $ret[$key] is not an array you try to merge an scalar value with an array - the result is not defined (incompatible arrays)
                        // in this case the call will trigger an E_USER_WARNING and the $ret[$key] will be null.
                        $ret[$key] = $this->array_merge_recursive2($ret[$key], $value);
                    }
                    else {
                        $ret[$key] = $value;
                    }
               // }
            }
        }

        return $ret;
    }

    public function setGroupPath($groupPath)
    {
        $this->groupPath = $groupPath;
    }

    public function getGroupPath()
    {
        return $this->groupPath;
    }

    public function setRoleId($roleId)
    {
        $this->roleId = $roleId;
    }

    public function getRoleId()
    {
        return $this->roleId;
    }

    public function setRoleLabel($roleLabel)
    {
        $this->roleLabel = $roleLabel;
    }

    public function getRoleLabel()
    {
        return $this->roleLabel;
    }

    /**
     * @param array $specificRights
     */
    public function setAutoApplies($specificRights){
        $this->autoApplies = $specificRights;
    }

    /**
     * @param string $specificRight
     * @return boolean
     */
    public function autoAppliesTo($specificRight){
        return in_array($specificRight, $this->autoApplies);
    }
}