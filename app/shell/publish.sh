#!/usr/bin/env bash

export PUBLIC_SRC
PUBLIC_SRC=./public
export RESOURCES_SRC
RESOURCES_SRC=./src/Resources/public
export VENDOR_SRC
VENDOR_SRC=./node_modules
printf "\\n\\tSyncing Public Vendors\\n\\n"
[ -d "${PUBLIC_SRC}" ] || mkdir ${PUBLIC_SRC}
[ -d "${PUBLIC_SRC}/scripts/jquery" ] || mkdir -pv "${PUBLIC_SRC}/scripts/jquery"
cp -rv ./node_modules/jquery/dist/jquery.* ${PUBLIC_SRC}/scripts/jquery
[ -d "${PUBLIC_SRC}/scripts/jquery-resize" ] || mkdir -pv "${PUBLIC_SRC}/scripts/jquery-resize"
cp -rv ./node_modules/jquery-debounced-and-throttled-resize/jquery.* ${PUBLIC_SRC}/scripts/jquery-resize
cp -rv ./node_modules/jquery-resizable-dom/dist/jquery-* ${PUBLIC_SRC}/scripts/jquery-resize
cp -rv ./node_modules/tachyons/css ${PUBLIC_SRC}/styles
cp -rv ./node_modules/font-awesome/css/font-awesome.min.css ${PUBLIC_SRC}/styles
cp -rv ./node_modules/font-awesome/fonts ${PUBLIC_SRC}
[ -d "${PUBLIC_SRC}/images/jquery-resize" ] || mkdir -pv "${PUBLIC_SRC}/images/jquery-resize"
cp -rv ./node_modules/jquery-resizable-dom/assets/* ${PUBLIC_SRC}/images/jquery-resize
printf "\\n\\tSyncing Public Files\\n\\n"
[ -d "${PUBLIC_SRC}" ] && cp -r ${RESOURCES_SRC}/*.php ${PUBLIC_SRC}
[ -d "${PUBLIC_SRC}/fonts" ] || mkdir -pv "${PUBLIC_SRC}/fonts"
[ -d "${RESOURCES_SRC}/fonts" ] && cp -r ${RESOURCES_SRC}/fonts/* ${PUBLIC_SRC}/fonts
[ -d "${PUBLIC_SRC}/images" ] || mkdir -pv "${PUBLIC_SRC}/images"
[ -d "${RESOURCES_SRC}/images" ] && cp -r ${RESOURCES_SRC}/images/* ${PUBLIC_SRC}/images
[ -d "${PUBLIC_SRC}/scripts" ] || mkdir -pv "${PUBLIC_SRC}/scripts"
[ -d "${RESOURCES_SRC}/scripts" ] && cp -r ${RESOURCES_SRC}/scripts/* ${PUBLIC_SRC}/scripts
[ -d "${PUBLIC_SRC}/styles" ] || mkdir -pv "${PUBLIC_SRC}/styles"
[ -d "${RESOURCES_SRC}/styles" ] && cp -r ${RESOURCES_SRC}/styles/* ${PUBLIC_SRC}/styles
printf "\\n\\tPurging BackOffice Vendors\\n\\n"
rm -rf ./vendor && printf "\\tOk!\\n" || printf "\\tNothing to do.\\n"