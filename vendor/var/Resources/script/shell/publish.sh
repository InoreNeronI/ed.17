#!/usr/bin/env sh

test $# == 2 || exit 1
export HTDOCS_DIR
HTDOCS_DIR=$1/web
export PUBLIC_DIR
PUBLIC_DIR=$1/vendor/$2/Resources/public
export NPM_DIR
NPM_DIR=$1/vendor/$2/Resources/config/node_modules

printf "\\n\\tSyncing Public Vendors\\n\\n"
[ -d "${HTDOCS_DIR}" ] || mkdir ${HTDOCS_DIR}

printf "\\n\\tSyncing Public Files\\n\\n"
cp -rv ${PUBLIC_DIR}/*.php ${HTDOCS_DIR}

printf "\\n\\tSyncing Fonts\\n\\n"
#[ -d "${HTDOCS_DIR}/fonts" ] || mkdir -pv "${HTDOCS_DIR}/fonts"
cp -rv ${NPM_DIR}/font-awesome/fonts ${HTDOCS_DIR}
#cp -rv ${PUBLIC_DIR}/fonts/* ${HTDOCS_DIR}/fonts

printf "\\n\\tSyncing Images\\n\\n"
[ -d "${HTDOCS_DIR}/images" ] || mkdir -pv "${HTDOCS_DIR}/images"
[ -d "${HTDOCS_DIR}/images/jquery-resize" ] || mkdir -pv "${HTDOCS_DIR}/images/jquery-resize"
cp -rv ${NPM_DIR}/jquery-resizable-dom/assets/* ${HTDOCS_DIR}/images/jquery-resize
cp -rv ${PUBLIC_DIR}/images/* ${HTDOCS_DIR}/images

printf "\\n\\tSyncing Scripts\\n\\n"
[ -d "${HTDOCS_DIR}/scripts" ] || mkdir -pv "${HTDOCS_DIR}/scripts"
[ -d "${HTDOCS_DIR}/scripts/jquery" ] || mkdir -pv "${HTDOCS_DIR}/scripts/jquery"
cp -rv ${NPM_DIR}/jquery/dist/jquery*min.* ${HTDOCS_DIR}/scripts/jquery
#cp -rv ${NPM_DIR}/jquery-file-download/src/Scripts/jquery.fileDownload.js ${HTDOCS_DIR}/scripts
[ -d "${HTDOCS_DIR}/scripts/jquery-resize" ] || mkdir -pv "${HTDOCS_DIR}/scripts/jquery-resize"
cp -rv ${NPM_DIR}/jquery-debounced-and-throttled-resize/jquery.* ${HTDOCS_DIR}/scripts/jquery-resize
cp -rv ${NPM_DIR}/jquery-resizable-dom/dist/jquery-* ${HTDOCS_DIR}/scripts/jquery-resize
cp -rv ${PUBLIC_DIR}/scripts/* ${HTDOCS_DIR}/scripts

printf "\\n\\tSyncing Styles\\n\\n"
[ -d "${HTDOCS_DIR}/styles" ] || mkdir -pv "${HTDOCS_DIR}/styles"
cp -rv ${NPM_DIR}/tachyons/css/tachyons.min.* ${HTDOCS_DIR}/styles
cp -rv ${NPM_DIR}/font-awesome/css/font-awesome.min.* ${HTDOCS_DIR}/styles
#cp -rv ${PUBLIC_DIR}/styles/* ${HTDOCS_DIR}/styles