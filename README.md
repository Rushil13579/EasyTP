# EasyTP
A TP request plugin for PocketMine 3.0.0 and above

## Commands
- /tpa:
    aliases: [/tpask, /tpo, /tpover]
    description: Send a tp request to a fellow player

- /tpahere:
    aliases: none
    description: Send a tphere request to a fellow player

- /tpaccept:
    aliases: [/tpyes, /tpok]
    descripiton: Accept a tp request from a player

- /tpdeny:
    aliases: [/tpno, /tpdecline]
    description: Decline a tp request from a player

## Configuration 

### List of levels where the commands of this plugin are banned
no-easytp-levels:
  - world1
  - world2

### Cooldown for the tpa/tpahere commands
### Must me a numeric value
### Set to '0' to disable
tp-cooldown: 15
