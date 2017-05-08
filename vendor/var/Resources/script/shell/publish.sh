#!/usr/bin/env bash

test $# == 2 || exit 1
export HTDOCS_DIR
HTDOCS_DIR=$1/web
export PUBLIC_DIR
PUBLIC_DIR=$1/vendor/var/Resources/public

printf "\\n\\tSyncing Public Vendors\\n\\n"
[ -d "${HTDOCS_DIR}" ] || mkdir ${HTDOCS_DIR}
[ -d "${HTDOCS_DIR}/scripts/jquery" ] || mkdir -pv "${HTDOCS_DIR}/scripts/jquery"
cp -rv $2/node_modules/jquery/dist/jquery.* ${HTDOCS_DIR}/scripts/jquery
#cp -rv $2/node_modules/jquery-file-download/src/Scripts/jquery.fileDownload.js ${HTDOCS_DIR}/scripts
[ -d "${HTDOCS_DIR}/scripts/jquery-resize" ] || mkdir -pv "${HTDOCS_DIR}/scripts/jquery-resize"
cp -rv $2/node_modules/jquery-debounced-and-throttled-resize/jquery.* ${HTDOCS_DIR}/scripts/jquery-resize
cp -rv $2/node_modules/jquery-resizable-dom/dist/jquery-* ${HTDOCS_DIR}/scripts/jquery-resize
cp -rv $2/node_modules/tachyons/css ${HTDOCS_DIR}/styles
cp -rv $2/node_modules/font-awesome/css/font-awesome.min.css ${HTDOCS_DIR}/styles
cp -rv $2/node_modules/font-awesome/fonts ${HTDOCS_DIR}
[ -d "${HTDOCS_DIR}/images/jquery-resize" ] || mkdir -pv "${HTDOCS_DIR}/images/jquery-resize"
cp -rv $2/node_modules/jquery-resizable-dom/assets/* ${HTDOCS_DIR}/images/jquery-resize
printf "\\n\\tSyncing Public Files\\n\\n"
[ -d "${HTDOCS_DIR}" ] && cp -rv ${PUBLIC_DIR}/*.php ${HTDOCS_DIR}
[ -d "${HTDOCS_DIR}/fonts" ] || mkdir -pv "${HTDOCS_DIR}/fonts"
[ -d "${PUBLIC_DIR}/fonts" ] && cp -rv ${PUBLIC_DIR}/fonts/* ${HTDOCS_DIR}/fonts
[ -d "${HTDOCS_DIR}/images" ] || mkdir -pv "${HTDOCS_DIR}/images"
[ -d "${PUBLIC_DIR}/images" ] && cp -rv ${PUBLIC_DIR}/images/* ${HTDOCS_DIR}/images
[ -d "${HTDOCS_DIR}/scripts" ] || mkdir -pv "${HTDOCS_DIR}/scripts"
[ -d "${PUBLIC_DIR}/scripts" ] && cp -rv ${PUBLIC_DIR}/scripts/* ${HTDOCS_DIR}/scripts
[ -d "${HTDOCS_DIR}/styles" ] || mkdir -pv "${HTDOCS_DIR}/styles"
[ -d "${PUBLIC_DIR}/styles" ] && cp -rv ${PUBLIC_DIR}/styles/* ${HTDOCS_DIR}/style
exit 0