#!/bin/bash

green=`tput setaf 2`
yellow=`tput setaf 3`
reset=`tput sgr0`

#Project GIT
read -p "${yellow}?${reset} Geben sie den Namen des neuen .GIT an: " gitname
echo -e "${green}\u2714${reset} Projekt wird vorbereitet"
echo -e "${green}\u2714${reset} .GIT-Repos werden verknüpft"
rm -rf .git
git init
git add --all
git remote add origin $gitname
git remote add upstream git@github.com:shopware/production.git
echo -e "${green}\u2714${reset} check Shopware"
git fetch upstream
read -p "${yellow}?${reset} Welche Shopware Version soll verwendet werden?: " gitbranch
echo -e "${green}\u2714${reset} Merge Shopware Version"
git merge -X theirs upstream/$gitbranch
echo -e "${green}\u2714${reset} Merge abgeschlossen!"
git add --all
echo -e "${green}\u2714${reset} Commit .git"
git commit -m "Project Start"
git branch -M main
echo -e "${green}\u2714${reset} upload zum .GIT-Repo"
#add new origin
git push -u origin main --force
echo -e "${green}\u2714${reset} Projekt angelegt"

echo -e "${green}---------------------------------${reset}"
echo -e "${green}\u2714${reset} Prepare Remote Server"
read -p "${yellow}?${reset} SSH USER: " sshusername
read -p "${yellow}?${reset} SS HOST: " sshhost
ssh-keygen -t rsa -b 4096 -f ssh_remote
ssh-copy-id -i ssh_remote.pub $sshusername@$sshhost
rm ssh_remote.pub

echo -e "${green}\u2714${reset} SSH verbunden"
echo -e "${green}---------------------------------${reset}"
echo -e "${yellow}!!! Bitte legen Sie SECRETS GIT an !!!${reset}"
echo -e "${yellow}!!! DEPLOYMENT_SERVER:  ${sshhost}!!!${reset}"
echo -e "${yellow}!!! SSH_PRIVATE_KEY:  cat >> ssh_remote!!!${reset}"


