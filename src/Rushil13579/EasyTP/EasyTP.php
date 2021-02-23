<?php

namespace Rushil13579\EasyTP;

use pocketmine\{Player, Server};

use pocketmine\plugin\PluginBase;

use pocketmine\command\{Command, CommandSender};

use pocketmine\utils\Config;

class EasyTP extends PluginBase {

  public $tp = [];
  public $tpcd = [];

  public function onEnable(){
    $this->saveDefaultConfig();
    $this->getResource('config.yml');
  }

  public function levelCheck($player){
    $level = $player->getLevel()->getName();
    if(in_array($level, $this->getConfig()->get('no-tp-levels'))){
      $player->sendMessage('§cThis command is banned in this level');
      return null;
    }
    return true;
  }

  public function cooldownCheck($player){
    $tpcd = $this->getConfig()->get('tp-cooldown');
    if(!is_numeric($tpcd)){
      $this->getLogger()->warning('§ctp-cooldown isn\'t numeric');
      $player->sendMessage('§cError, please contact server administrators');
      return null;
    }

    if(!isset($this->tpcd[$player->getName()])){
      $this->tpcd[$player->getName()] = time() + $tpcd;
    } else {
      if(time() < $this->tpcd[$player->getName()]){
        $rem = $this->tpcd[$player->getName()] - time();
        $player->sendMessage("§cYou are on cooldown for $rem seconds");
        return null;
      } else {
        unset($this->tpcd[$player->getName()]);
        $this->tpcd[$player->getName()] = time() + $tpcd;
      }
    }
    return true;
  }

  public function tpRequest($player, $target, $type){
    $levelcheck = $this->levelCheck($player);
    if($levelcheck === null){
      return null;
    }

    $cooldowncheck = $this->cooldownCheck($player);
    if($cooldowncheck === null){
      return null;
    }

    if($type == 'tpa'){
      $this->tp[$player->getName()] = ['tpa', $target->getName()];
      $player->sendMessage("§aYou have sent a tpa request to §6" . $target->getName());
      $target->sendMessage("§aYou have received a tpa request from §6" . $player->getName() . ". §aYou can accept the request using /tpaccept " . $player->getName());
    }

    if($type == 'tpahere'){
      $this->tp[$player->getName()] = ['tpahere', $target->getName()];
      $player->sendMessage("§aYou have sent a tpahere request to §6" . $target->getName());
      $target->sendMessage("§aYou have received a tpahere request from §6" . $player->getName() . ". §aYou can accept the request using /tpaccept " . $player->getName());
    }
  }

  public function tpAnswer($player, $target, $type){
    $levelcheck = $this->levelCheck($player);
    if($levelcheck === null){
      return null;
    }

    if(!isset($this->tp[$target->getName()])){
      $player->sendMessage("§cYou don't have a tp request from " . $target->getName());
      return null;
    }

    if($this->tp[$target->getName()][1] !== $player->getName()){
      $player->sendMessage("§cYou don't have a tp request from " . $target->getName());
      return null;
    }

    $tptype = $this->tp[$target->getName()][0];
    if($type == 'tpaccept'){
      if($tptype == 'tpa'){
        unset($this->tp[$target->getName()]);
        $target->teleport($player);
        $player->sendMessage('§aTeleporting...');
      }
      if($tptype == 'tpahere'){
        unset($this->tp[$target->getName()]);
        $player->teleport($target);
        $target->sendMessage('§aTeleporting...');
      }
    }
    if($type == 'tpdeny'){
      unset($this->tp[$target->getName()]);
      $player->sendMessage('§cYou have denied a tp request from ' . $target->getName());
      $target->sendMessage("§c" . $player->getName() . " has denied your tp request");
    }
  }

  public function onCommand(CommandSender $s, Command $cmd, String $label, Array $args) : bool {

    switch($cmd->getName()){
      case "tpa":

      if(!$s instanceof Player){
        $s->sendMessage('§cPlease use this command in-game');
        return false;
      }

      if(!isset($args[0])){
        $s->sendMessage('§cUsage: /tpa [player]');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) === null or !$this->getServer()->getPlayer($args[0])->isOnline()){
        $s->sendMessage('§cPlayer not found');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) == $s->getName()){
        $s->sendMessage('§cYou cannot send a tp request to your self');
        return false;
      }

      $t = $this->getServer()->getPlayer($args[0]);
      $type = 'tpa';
      $this->tpRequest($s, $t, $type);
    }

    switch($cmd->getName()){
      case "tpahere":

      if(!$s instanceof Player){
        $s->sendMessage('§cPlease use this command in-game');
        return false;
      }

      if(!isset($args[0])){
        $s->sendMessage('§cUsage: /tpahere [player]');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) === null or !$this->getServer()->getPlayer($args[0])->isOnline()){
        $s->sendMessage('§cPlayer not found');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) == $s->getName()){
        $s->sendMessage('§cYou cannot send a tp request to your self');
        return false;
      }

      $t = $this->getServer()->getPlayer($args[0]);
      $type = 'tpahere';
      $this->tpRequest($s, $t, $type);
    }

    switch($cmd->getName()){
      case "tpaccept":

      if(!$s instanceof Player){
        $s->sendMessage('§cPlease use this command in-game');
        return false;
      }

      if(!isset($args[0])){
        $s->sendMessage('§cUsage: /tpaccept [player] | /tpyes [player]');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) === null or !$this->getServer()->getPlayer($args[0])->isOnline()){
        $s->sendMessage('§cPlayer not found');
        return false;
      }

      $t = $this->getServer()->getPlayer($args[0]);
      $type = 'tpaccept';
      $this->tpAnswer($s, $t, $type);
    }

    switch($cmd->getName()){
      case "tpdeny":

      if(!$s instanceof Player){
        $s->sendMessage('§cPlease use this command in-game');
        return false;
      }

      if(!isset($args[0])){
        $s->sendMessage('§cUsage: /tpdeny [player] | /tpno [player]');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) === null or !$this->getServer()->getPlayer($args[0])->isOnline()){
        $s->sendMessage('§cPlayer not found');
        return false;
      }

      $t = $this->getServer()->getPlayer($args[0]);
      $type = 'tpdeny';
      $this->tpAnswer($s, $t, $type);
    }
    return true;
  }
}
