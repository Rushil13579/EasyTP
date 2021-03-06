<?php

namespace Rushil13579\EasyTP;

use pocketmine\{Player, Server};

use pocketmine\plugin\PluginBase;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\command\{Command, CommandSender};

use pocketmine\level\Level;

use pocketmine\utils\{Config, TextFormat as C};

class EasyTP extends PluginBase implements Listener {

  public $cfg;
  public $block;

  public $tp = [];
  public $tpcd = [];
  public $status = [];

  const PREFIX = '§3[§bEasyTP§3]';

  public function onEnable(){
    $this->getServer()->getPluginManager()->registerEvents($this, $this);

    $this->saveDefaultConfig();
    $this->getResource('config.yml');

    $this->cfg = $this->getConfig();

    $this->block = new Config($this->getDataFolder() . 'BlockedPlayers.yml', Config::YAML);

    $this->versionCheck();
  }

  public function versionCheck(){
    if($this->cfg->get('version') !== '1.3.0'){
      $this->getLogger()->warning(self::PREFIX . ' §cThe current configuration file is outdated. Please delete it and restart the server to install the latest configuration file!');
      $this->getServer()->getPluginManager()->disablePlugin($this);
    }
  }

  public function onJoin(PlayerJoinEvent $ev){
    $player = $ev->getPlayer();
    if(!$this->block->exists($player->getName())){
      $this->block->set($player->getName(), []);
      $this->block->save();
    }
  }

  public function levelCheck($player){
    $level = $player->getLevel()->getName();

    if(in_array($level, $this->cfg->get('blacklist-levels'))){
      $player->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('blacklisted-level-msg'))));
      return null;
    }

    foreach($this->cfg->get('blacklist-contains') as $key){
      if(strpos(strtolower($level), strtolower($key)) !== false){
        $player->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('blacklisted-level-msg'))));
        return null;
      }
    }
    return true;
  }

  public function cooldownCheck($player){
    $tpcd = $this->cfg->get('tp-cooldown');
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
        $player->sendMessage(C::colorize(str_replace(['{PREFIX}', '{REMAINING}'], [self::PREFIX, $rem], $this->cfg->get('tp-cooldown-msg'))));
        return null;
      } else {
        unset($this->tpcd[$player->getName()]);
        $this->tpcd[$player->getName()] = time() + $tpcd;
      }
    }
    return true;
  }

  public function statusCheck($target){
    if(!isset($this->status[$target->getName()])){
      return ' ';
    }
    return null;
  }

  public function blockCheck($player, $target){
    if(!in_array($player->getName(), $this->block->get($target->getName()))){
      return ' ';
    }
    return null;
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

    $statuscheck = $this->statusCheck($target);
    if($statuscheck === null){
      $player->sendMessage(C::colorize(str_replace(['{PREFIX}', '{RECEIVER}'], [self::PREFIX, $target->getName()], $this->cfg->get('tp-closed-msg'))));
      return null;
    }

    $blockcheck = $this->blockCheck($player, $target);
    if($blockcheck === null){
      $player->sendMessage(C::colorize(str_replace(['{PREFIX}', '{RECEIVER}'], [self::PREFIX, $target->getName()], $this->cfg->get('tp-blocked-msg'))));
      return null;
    }

    if($type == 'tpa'){
      $this->tp[$player->getName()] = ['tpa', $target->getName()];
      $player->sendMessage(C::colorize(str_replace(['{PREFIX}', '{RECEIVER}'], [self::PREFIX, $target->getName()], $this->cfg->get('tpa-request-sender-msg'))));
      $target->sendMessage(C::colorize(str_replace(['{PREFIX}', '{REQUESTER}'], [self::PREFIX, $player->getName()], $this->cfg->get('tpa-request-receiver-msg'))));
    }

    if($type == 'tpahere'){
      $this->tp[$player->getName()] = ['tpahere', $target->getName()];
      $player->sendMessage(C::colorize(str_replace(['{PREFIX}', '{RECEIVER}'], [self::PREFIX, $target->getName()], $this->cfg->get('tpahere-request-sender-msg'))));
      $target->sendMessage(C::colorize(str_replace(['{PREFIX}', '{REQUESTER}'], [self::PREFIX, $player->getName()], $this->cfg->get('tpahere-request-receiver-msg'))));
    }
  }

  public function tpAnswer($player, $target, $type){
    $levelcheck = $this->levelCheck($player);
    if($levelcheck === null){
      return null;
    }

    if(!isset($this->tp[$target->getName()])){
      $player->sendMessage(C::colorize(str_replace(['{PREFIX}', '{TARGET}'], [self::PREFIX, $target->getName()], $this->cfg->get('no-tp-request-exists-msg'))));
      return null;
    }

    if($this->tp[$target->getName()][1] !== $player->getName()){
      $player->sendMessage(C::colorize(str_replace(['{PREFIX}', '{TARGET}'], [self::PREFIX, $target->getName()], $this->cfg->get('no-tp-request-exists-msg'))));
      return null;
    }

    $tptype = $this->tp[$target->getName()][0];
    if($type == 'tpaccept'){
      if($tptype == 'tpa'){
        unset($this->tp[$target->getName()]);
        $target->teleport($player);
        $player->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('teleporting-msg'))));
      }
      if($tptype == 'tpahere'){
        unset($this->tp[$target->getName()]);
        $player->teleport($target);
        $target->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('teleporting-msg'))));
      }
    }
    if($type == 'tpdeny'){
      unset($this->tp[$target->getName()]);
      $player->sendMessage(C::colorize(str_replace(['{PREFIX}', '{REQUESTER}'], [self::PREFIX, $target->getName()], $this->cfg->get('request-denied-receiver-msg'))));
      $target->sendMessage(C::colorize(str_replace(['{PREFIX}', '{RECEIVER}'], [self::PREFIX, $player->getName()], $this->cfg->get('request-denied-requester-msg'))));
    }
  }

  public function onCommand(CommandSender $s, Command $cmd, String $label, Array $args) : bool {

    switch($cmd->getName()){
      case 'tpa':

      if(!$s instanceof Player){
        $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
        return false;
      }

      if(!isset($args[0])){
        $s->sendMessage(self::PREFIX . ' §cUsage: /tpa [player] | /tpask [player]');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) === null or !$this->getServer()->getPlayer($args[0])->isOnline()){
        $s->sendMessage(self::PREFIX . ' §cPlayer not found');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) == $s->getName()){
        $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('no-self-tp-request'))));
        return false;
      }

      $t = $this->getServer()->getPlayer($args[0]);
      $type = 'tpa';
      $this->tpRequest($s, $t, $type);
    }

    switch($cmd->getName()){
      case 'tpahere':

      if(!$s instanceof Player){
        $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
        return false;
      }

      if(!isset($args[0])){
        $s->sendMessage(self::PREFIX . ' §cUsage: /tpahere [player] | /tph [player]');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) === null or !$this->getServer()->getPlayer($args[0])->isOnline()){
        $s->sendMessage(self::PREFIX . ' §cPlayer not found');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) == $s->getName()){
        $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('no-self-tp-request'))));
        return false;
      }

      $t = $this->getServer()->getPlayer($args[0]);
      $type = 'tpahere';
      $this->tpRequest($s, $t, $type);
    }

    switch($cmd->getName()){
      case 'tpaccept':

      if(!$s instanceof Player){
        $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
        return false;
      }

      if(!isset($args[0])){
        $s->sendMessage(self::PREFIX . ' §cUsage: /tpaccept [player] | /tpyes [player] | /tpok [player]');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) === null or !$this->getServer()->getPlayer($args[0])->isOnline()){
        $s->sendMessage(self::PREFIX . ' §cPlayer not found');
        return false;
      }

      $t = $this->getServer()->getPlayer($args[0]);
      $type = 'tpaccept';
      $this->tpAnswer($s, $t, $type);
    }

    switch($cmd->getName()){
      case 'tpdeny':

      if(!$s instanceof Player){
        $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
        return false;
      }

      if(!isset($args[0])){
        $s->sendMessage(self::PREFIX . ' §cUsage: /tpdeny [player] | /tpno [player] | /tpdecline [player]');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) === null or !$this->getServer()->getPlayer($args[0])->isOnline()){
        $s->sendMessage(self::PREFIX . ' §cPlayer not found');
        return false;
      }

      $t = $this->getServer()->getPlayer($args[0]);
      $type = 'tpdeny';
      $this->tpAnswer($s, $t, $type);
    }

    switch($cmd->getName()){
      case 'tpwithdraw':

        if(!$s instanceof Player){
          $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
          return false;
        }

        if(!isset($this->tp[$s->getName()])){
          $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('tp-request-absent-msg'))));
          return false;
        }

        unset($this->tp[$s->getName()]);
        $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('tp-request-withdrawn-msg'))));
    }

    switch($cmd->getName()){
      case 'tphere':

      if(!$s instanceof Player){
        $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
        return false;
      }

      if(!$s->hasPermission('easytp.command.tphere') and !$s->hasPermission('easytp.command.*')){
        $s->sendMessage(self::PREFIX . ' §cYou do not have permission to use this command');
        return false;
      }

      if(!isset($args[0])){
        $s->sendMessage(self::PREFIX . ' §cUsage: /tphere [player]');
        return false;
      }

      if($this->getServer()->getPlayer($args[0]) === null){
        $s->sendMessage(self::PREFIX . ' §cPlayer not found');
        return false;
      }

      $player = $this->getServer()->getPlayer($args[0]);

      $player->teleport($s);
      $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('teleporting-msg'))));
    }

    switch($cmd->getName()){
      case 'tpall':

      if(!$s instanceof Player){
        $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
        return false;
      }

      if(!$s->hasPermission('easytp.command.tpall') and !$s->hasPermission('easytp.command.*')){
        $s->sendMessage(self::PREFIX . ' §cYou do not have permission to use this command');
        return false;
      }

      foreach($this->getServer()->getOnlinePlayers() as $player){
        $player->teleport($s);
      }

      $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('teleporting-msg'))));
    }

    switch($cmd->getName()){
      case 'tpopen':
      
        if(!$s instanceof Player){
          $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
          return false;
        }

        unset($this->status[$s->getName()]);
        
        $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('tp-turned-open-msg'))));
    }

    switch($cmd->getName()){
      case 'tpclose':
      
        if(!$s instanceof Player){
          $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
          return false;
        }

        $this->status[$s->getName()] = 'open';
        
        $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('tp-turned-close-msg'))));
    }

    switch($cmd->getName()){
      case 'tpblock':

        if(!$s instanceof Player){
          $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
          return false;
        }

        if(!isset($args[0])){
          $s->sendMessage(self::PREFIX . ' §cUsage: /tpblock [player]');
          return false;
        }

        if($this->getServer()->getPlayer($args[0]) === null){
          $s->sendMessage(self::PREFIX . ' §cPlayer not found');
          return false;
        }

        $player = $this->getServer()->getPlayer($args[0]);

        if(empty($this->block->get($s->getName()))){
          $list[] = $player->getName();
          $this->block->set($s->getName(), $list);
          $this->block->save();

          $s->sendMessage(C::colorize(str_replace(['{PREFIX}', '{BLOCKED_PLAYER}'], [self::PREFIX, $player->getName()], $this->cfg->get('blocked-player-msg'))));
          return false;
        }

        if(in_array($player->getName(), $this->block->get($s->getName()))){
          $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('already-blocked-msg'))));
          return false;
        }

        $list = $this->block->get($s->getName());
        $list[] = $player->getName();
        $this->block->set($s->getName(), $list);
        $this->block->save();

        $s->sendMessage(C::colorize(str_replace(['{PREFIX}', '{BLOCKED_PLAYER}'], [self::PREFIX, $player->getName()], $this->cfg->get('blocked-player-msg'))));
    }

    switch($cmd->getName()){
      case 'tpunblock':

        if(!$s instanceof Player){
          $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
          return false;
        }

        if(!isset($args[0])){
          $s->sendMessage(self::PREFIX . ' §cUsage: /tpblock [player]');
          return false;
        }

        if($this->getServer()->getPlayer($args[0]) === null){
          $s->sendMessage(self::PREFIX . ' §cPlayer not found');
          return false;
        }

        $player = $this->getServer()->getPlayer($args[0]);

        if(empty($this->block->get($s->getName()))){
          $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('not-blocked-msg'))));
          return false;
        }

        if(!in_array($player->getName(), $this->block->get($s->getName()))){
          $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('not-blocked-msg'))));
          return false;
        }

        $list = $this->block->get($s->getName());
        $key = array_search($player->getName(), $list);
        array_splice($list, $key, 1);
        $this->block->set($s->getName(), $list);
        $this->block->save();
        $s->sendMessage(C::colorize(str_replace(['{PREFIX}', '{UNBLOCKED_PLAYER}'], [self::PREFIX, $player->getName()], $this->cfg->get('unblocked-player-msg'))));
    }

    switch($cmd->getName()){
      case 'tpworld':

        if(!$s instanceof Player){
          $s->sendMessage(self::PREFIX . ' §cPlease use this command in-game');
          return false;
        }
  
        if(!$s->hasPermission('easytp.command.tpworld') and !$s->hasPermission('easytp.command.*')){
          $s->sendMessage(self::PREFIX . ' §cYou do not have permission to use this command');
          return false;
        }

        if(!isset($args[0])){
          $s->sendMessage(self::PREFIX . ' &cUsage: /tpworld [world]');
          return false;
        }

        $worldname = $args[0];

        if(!$this->getServer()->isLevelLoaded($worldname)){
          $s->sendMessage(self::PREFIX . ' §cInvalid worldname');
          return false;
        }

        $s->teleport($this->getServer()->getLevelByName($worldname)->getSpawnLocation());
        $s->sendMessage(C::colorize(str_replace('{PREFIX}', self::PREFIX, $this->cfg->get('teleporting-msg'))));
    }
    return true;
  }
}
