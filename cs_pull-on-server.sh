#!/bin/sh

. .env

ssh $USER@$REMOTE 'git -C '$PLUGINPATH' pull origin main'
#copies the js, makes migrations, executes update()
ssh $USER@$REMOTE 'sudo -u www-data '$BINPATH'/bin/console plugin:update '$PLUGINNAME

# execute with www-data as user
ssh $USER@$REMOTE 'sudo -u www-data '$BINPATH'/bin/console cache:clear'

# execute with www-data as user
ssh $USER@$REMOTE  'sudo -u www-data '$BINPATH'/bin/console theme:compile'