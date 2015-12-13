#!/bin/bash

DRYRUN=0
if [ "$2" == "--go" ]; then
	DRYRUN=1
fi

# Return 0 if equals, 1 if $2 > $1 and 255 if $2 < $1
function checkver {
	V1=$1
	V2=$2
	V1A=(${V1//\./ })
	V2A=(${V2//\./ })
	i=0
	for v in ${V1A[@]}; do
		if [ ${#V2A[@]} -eq $i ]; then
			return 255
		fi
		if [ ${V2A[$i]} -lt $v ]; then
			return 255
		elif [ ${V2A[$i]} -gt $v ]; then
			return 1
		fi
		i=$(($i+1))
	done
	if [ $i -lt ${#V2A[@]} ]; then
		return 1
	fi
	return 0
}

CURVER=`wget http://upgrade.mikrotik.com/routeros/LATEST.6 -O- -q | awk -F' ' '{ print $1 }'`

for ADDR in $1; do

	ssh -l admin -q -o PasswordAuthentication=no -oStrictHostKeyChecking=no $ADDR "/system"
	if [ $? -ne 0 ]; then
		echo "Public key missing, copying..."
		scp $HOME/.ssh/id_dsa.pub admin@$ADDR:/
		ssh admin@$ADDR "/user ssh-keys import public-key-file=id_dsa.pub user=admin"
	fi

	PACKAGEROW=`ssh admin@$ADDR "/system package print" | grep routeros`
	RUNVER=`echo $PACKAGEROW | awk -F' ' '{ print $3 }' | sed 's/\x0d//'`
	PLATFORM=`echo $PACKAGEROW | awk -F' ' '{ print $2 }' | sed 's/\x0d//'`

	#Alternative method?
	#RUNVER=`ssh admin@$ADDR "/system package update print" | grep current-version | awk -F': ' '{ print $2 }' | sed 's/\x0d//'`

	checkver $RUNVER $CURVER
	VSTATUS=$?
	if [ $VSTATUS -eq 1 ]; then
		echo "Upgrade required ($RUNVER < $CURVER)"

		PACKAGENAME=$PLATFORM-$CURVER.npk
		if [ ! -e "/tmp/$PACKAGENAME" ]; then
			echo "Downloading $PACKAGENAME..."
			wget http://download2.mikrotik.com/routeros/$CURVER/$PACKAGENAME -O /tmp/$PACKAGENAME -q
		fi

		if [ $DRYRUN -eq 1 ]; then
			echo "Sending upgrade..."
			scp /tmp/$PACKAGENAME admin@$ADDR:/

			echo "Rebooting device"
			ssh $ADDR -l admin "/system reboot"
		fi
	elif [ $VSTATUS -eq 0 ]; then
		echo "Latest version already installed ($CURVER)"
	elif [ $VSTATUS -eq 255 ]; then
		echo "Newer version than website... ($CURVER < $RUNVER)"
	fi

done
