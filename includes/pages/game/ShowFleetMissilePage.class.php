<?php

/**
*  ultimateXnova
*  based on 2moons by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package ultimateXnova
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2022 Koray Karakuş <koraykarakus@yahoo.com>
 * @copyright 2024 Pfahli (https://github.com/Pfahli)
 * @licence MIT
 * @version 1.8.x Koray Karakuş <koraykarakus@yahoo.com>
 * @link https://github.com/ultimateXnova/ultimateXnova
 */

class ShowFleetMissilePage extends AbstractGamePage
{
	public static $requireModule = MODULE_MISSILEATTACK;

	function __construct()
	{
		parent::__construct();
	}

	public function show()
	{
		global $USER, $PLANET, $LNG, $reslist, $resource;

		$targetGalaxy = HTTP::_GP('galaxy', 0);
		$targetSystem = HTTP::_GP('system', 0);
		$targetPlanet = HTTP::_GP('planet', 0);
		$targetType = HTTP::_GP('type', 0);
		$anz = min(HTTP::_GP('SendMI',0), $PLANET['interplanetary_misil']);
		$primaryTarget = HTTP::_GP('Target', 0);

    $db	= Database::get();

    $sql = "SELECT id, id_owner FROM %%PLANETS%%
    WHERE universe = :universe AND galaxy = :targetGalaxy
    AND `system` = :targetSystem AND planet = :targetPlanet AND planet_type = :targetType;";

    $target = $db->selectSingle($sql, array(
        ':universe' => Universe::current(),
        ':targetGalaxy' => $targetGalaxy,
        ':targetSystem' => $targetSystem,
        ':targetPlanet' => $targetPlanet,
        ':targetType'   => $targetType
    ));

    $Range				= FleetFunctions::GetMissileRange($USER[$resource[117]]);
		$systemMin			= $PLANET['system'] - $Range;
		$systemMax			= $PLANET['system'] + $Range;

		$error = array();

		if (IsVacationMode($USER))
		{
			$error[] = $LNG['fl_vacation_mode_active'];
		}

		if ($PLANET['silo'] < 4)
		{
			$error[] = $LNG['ma_silo_level'];
		}

		if ($USER['impulse_motor_tech'] == 0)
		{
			$error[] = $LNG['ma_impulse_drive_required'];
		}

		if ($targetGalaxy != $PLANET['galaxy'] || $targetSystem < $systemMin || $targetSystem > $systemMax){
			$error[] = $LNG['ma_not_send_other_galaxy'];
		}

		if (!$target)
		{
			$error[] = $LNG['ma_planet_doesnt_exists'];
		}

		if (!in_array($primaryTarget, $reslist['defense']) && $primaryTarget != 0)
		{
			$error[] = $LNG['ma_wrong_target'];
		}

		if ($PLANET['interplanetary_misil'] == 0)
		{
			$error[] = $LNG['ma_no_missiles'];
		}

		if ($anz <= 0)
		{
			$error[] = $LNG['ma_add_missile_number'];
		}

		if(empty($target)) {
			$target['id_owner'] = 0;
			$targetUser = array('onlinetime' => 0, 'banaday' => 0, 'urlaubs_modus' => 0, 'authattack' => 0);
		} else {
			$targetUser		= GetUserByID($target['id_owner'], array('onlinetime', 'banaday', 'urlaubs_modus', 'authattack'));
		}

		if (Config::get()->adm_attack == 1 && $targetUser['authattack'] > $USER['authlevel']){
			$error[] = $LNG['fl_admin_attack'];
		}

		if($targetUser['urlaubs_modus']){
			$error[] = $LNG['fl_in_vacation_player'];
		}

		$sql = "SELECT total_points FROM %%USER_POINTS%% WHERE id_owner = :ownerId;";

		$User2Points = $db->selectSingle($sql, array(
        ':ownerId'  => $target['id_owner']
    ));

		$sql	= 'SELECT total_points
		FROM %%USER_POINTS%%
		WHERE id_owner = :userId;';

		$USER	+= Database::get()->selectSingle($sql, array(
			':userId'	=> $USER['id'],
		));

    $IsNoobProtec	= CheckNoobProtec($USER, $User2Points, $targetUser);

		if ($IsNoobProtec['NoobPlayer']){
			$error[] = $LNG['fl_week_player'];
		}

		if ($IsNoobProtec['StrongPlayer']){
			$error[] = $LNG['fl_strong_player'];
		}

		if (!empty($error))
		{
			$errorText = "";
			foreach ($error as $currentErrorText) {
				$errorText .= $currentErrorText . "\n";
			}

			$this->printMessage($errorText);
		}

		$Duration		= FleetFunctions::GetMIPDuration($PLANET['system'], $targetSystem);

		$DefenseLabel 	= ($primaryTarget == 0) ? $LNG['ma_all'] : $LNG['tech'][$primaryTarget];

		$fleetArray		= array(503 => $anz);

		$fleetStartTime	= TIMESTAMP + $Duration;
		$fleetStayTime	= $fleetStartTime;
		$fleetEndTime	= $fleetStartTime;

		$fleetResource	= array(
			901	=> 0,
			902	=> 0,
			903	=> 0,
		);

		// saving planet avoids a bug if shipyard is producing interplanetary missiles
		$this->save();

		FleetFunctions::sendFleet($fleetArray, 10, $USER['id'], $PLANET['id'], $PLANET['galaxy'], $PLANET['system'],
			$PLANET['planet'], $PLANET['planet_type'], $target['id_owner'], $target['id'], $targetGalaxy, $targetSystem,
			$targetPlanet, $targetType, $fleetResource, $fleetStartTime, $fleetStayTime, $fleetEndTime, 0, $primaryTarget);

		$this->printMessage("<b>".$anz."</b>". $LNG['ma_missiles_sended'].$DefenseLabel);
	}
}
