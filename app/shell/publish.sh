#!/usr/bin/env bash

export UPLOAD_DIR
UPLOAD_DIR=./uploads
export PUBLIC_DIR
PUBLIC_DIR=./web
export RESOURCES_DIR
RESOURCES_DIR=./src/Resources/public
export VENDOR_DIR
VENDOR_DIR=./node_modules
printf "\\n\\tSyncing Public Vendors\\n\\n"
[ -d "${PUBLIC_DIR}" ] || mkdir ${PUBLIC_DIR}
[ -d "${PUBLIC_DIR}/scripts/jquery" ] || mkdir -pv "${PUBLIC_DIR}/scripts/jquery"
cp -rv ./node_modules/jquery/dist/jquery.* ${PUBLIC_DIR}/scripts/jquery
cp -rv ./node_modules/jquery-file-download/src/Scripts/jquery.fileDownload.js ${PUBLIC_DIR}/scripts
[ -d "${PUBLIC_DIR}/scripts/jquery-resize" ] || mkdir -pv "${PUBLIC_DIR}/scripts/jquery-resize"
cp -rv ./node_modules/jquery-debounced-and-throttled-resize/jquery.* ${PUBLIC_DIR}/scripts/jquery-resize
cp -rv ./node_modules/jquery-resizable-dom/dist/jquery-* ${PUBLIC_DIR}/scripts/jquery-resize
cp -rv ./node_modules/tachyons/css ${PUBLIC_DIR}/styles
cp -rv ./node_modules/font-awesome/css/font-awesome.min.css ${PUBLIC_DIR}/styles
cp -rv ./node_modules/font-awesome/fonts ${PUBLIC_DIR}
[ -d "${PUBLIC_DIR}/images/jquery-resize" ] || mkdir -pv "${PUBLIC_DIR}/images/jquery-resize"
cp -rv ./node_modules/jquery-resizable-dom/assets/* ${PUBLIC_DIR}/images/jquery-resize
printf "\\n\\tSyncing Public Files\\n\\n"
[ -d "${PUBLIC_DIR}" ] && cp -rv ${RESOURCES_DIR}/*.php ${PUBLIC_DIR}
[ -d "${PUBLIC_DIR}/fonts" ] || mkdir -pv "${PUBLIC_DIR}/fonts"
[ -d "${RESOURCES_DIR}/fonts" ] && cp -rv ${RESOURCES_DIR}/fonts/* ${PUBLIC_DIR}/fonts
[ -d "${PUBLIC_DIR}/images" ] || mkdir -pv "${PUBLIC_DIR}/images"
[ -d "${RESOURCES_DIR}/images" ] && cp -rv ${RESOURCES_DIR}/images/* ${PUBLIC_DIR}/images
[ -d "${PUBLIC_DIR}/scripts" ] || mkdir -pv "${PUBLIC_DIR}/scripts"
[ -d "${RESOURCES_DIR}/scripts" ] && cp -rv ${RESOURCES_DIR}/scripts/* ${PUBLIC_DIR}/scripts
[ -d "${PUBLIC_DIR}/styles" ] || mkdir -pv "${PUBLIC_DIR}/styles"
[ -d "${RESOURCES_DIR}/styles" ] && cp -rv ${RESOURCES_DIR}/styles/* ${PUBLIC_DIR}/styles
[ -d "${UPLOAD_DIR}" ] || mkdir -pv "${UPLOAD_DIR}"
printf "\\n\\tSetting Permissions Files\\n\\n"
chmod -Rv g+rw ${UPLOAD_DIR}
chown -Rv www-data.www-data ${UPLOAD_DIR} || true
#printf "\\n\\tPurging BackOffice Vendors\\n\\n"
#rm -rv ./vendor && printf "\\tOk!\\n" || printf "\\tNothing to do.\\n"