(function (OCA) {
    OCA.Dashvideoplayer = _.extend({}, OCA.Dashvideoplayer);

    OCA.AppSettings = null;
    OCA.Dashvideoplayer.Mimes = [];

    if (!OCA.Dashvideoplayer.AppName) {
        OCA.Dashvideoplayer = {
            AppName: "dashvideoplayer",
            frameSelector: null
        };
    }

    OCA.Dashvideoplayer.OpenPlayer = function (fileId, filePath) {
        var url = OC.generateUrl(
            "/apps/" + OCA.Dashvideoplayer.AppName + "/{fileId}",
            {
                fileId: fileId
            }
        );

        /*window.open(url, "Dash Player", "width=1200, height=600");
        return*/

        // create div element
        var divModalMaskExists = document.getElementById("divDashPlayerModal");
        if (!divModalMaskExists) {
            // Create modal mask
            var divModalMask = document.createElement("div");
            divModalMask.id = "divDashPlayerModal";
            divModalMask.setAttribute(
                "style",
                "position:fixed;z-index: 9998;top: 0;left: 0;display: block;width: 100%;height: 100%;background-color: rgba(0,0,0,0.7);"
            );

            // Create modal header
            var divModalHeader = document.createElement("div");
            divModalHeader.setAttribute(
                "style",
                "position: absolute;z-index: 10001;top: 0;right: 0;left: 0;display: flex !important;align-items: center;justify-content: center;width: 100%;height: 50px;transition: opacity 250ms, visibility 250ms; background-color: rgba(0,0,0,0.8);"
            );

            // Create icons menu
            var divIconsMenu = document.createElement("div");
            divIconsMenu.setAttribute(
                "style",
                "position: absolute;right: 0;display: flex;align-items: center;justify-content: flex-end;"
            );

            // append icons menu to modal header
            divModalHeader.appendChild(divIconsMenu);

            // Create close button
            var buttonClose = document.createElement("button");
            buttonClose.type = "button";
            buttonClose.innerHTML = "X";
            buttonClose.setAttribute(
                "style",
                "background-color: transparent; border-color: transparent; color: white; font-size: 16px"
            );

            buttonClose.onclick = function () {
                document.getElementById("iframeDashPlayerModal").src = "about:blank";
                document.getElementById('divDashPlayerModal').style.display = "none";
            };
            // append button to icons menu
            divIconsMenu.appendChild(buttonClose);

            // Create modal wrapper
            var divModalWrapper = document.createElement("div");
            divModalWrapper.setAttribute(
                "style",
                "display: flex;align-items: center;justify-content: center;box-sizing: border-box;width: 100%;height: 100%;"
            );

            // Create modal container
            var divModalContainer = document.createElement("div");
            divModalContainer.setAttribute(
                "style",
                "display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; cursor: pointer;"
            );
            divModalContainer.innerHTML =
                "<iframe id='iframeDashPlayerModal' width='75%' height='75%' src='" + url + "'></iframe>";

            // append modal container to modal wrapper
            divModalWrapper.appendChild(divModalContainer);

            // append modal header to modal mask
            divModalMask.appendChild(divModalHeader);

            // append modal wrapper to modal mask
            divModalMask.appendChild(divModalWrapper);

            // append modal mask to document
            document.body.appendChild(divModalMask);
        } else {
            document.getElementById("iframeDashPlayerModal").src = url;
            divModalMaskExists.style.display = "block";
        }
    };

    OCA.Dashvideoplayer.GetSettings = function (callbackSettings) {
        if (OCA.Dashvideoplayer.Mimes) {
            callbackSettings();
        } else {
            var url = OC.generateUrl(
                "apps/" + OCA.Dashvideoplayer.AppName + "/ajax/settings"
            );

            fetch(url, {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                credentials: "same-origin"
            })
                .then(function (response) {
                    if (response.status !== 200) {
                        console.log("Fetch error. Status Code: " + response.status);
                        return;
                    }

                    response.json().then(function (data) {
                        OCA.AppSettings = data.settings;
                        OCA.Dashvideoplayer.Mimes = data.formats;
                        callbackSettings();
                    });
                })
                .catch(function (err) {
                    console.log("Fetch Error: ", err);
                });
        }
    };

    OCA.Dashvideoplayer.FileList = {
        attach: function (fileList) {
            if (fileList.id == "trashbin") {
                return;
            }

            var registerfunc = function () {
                console.log("OCA.Dashvideoplayer.Mimes: ", OCA.Dashvideoplayer.Mimes);
                if (typeof OCA.Dashvideoplayer.Mimes != "object") return;

                for (const ext in OCA.Dashvideoplayer.Mimes) {
                    attr = OCA.Dashvideoplayer.Mimes[ext];
                    fileList.fileActions.registerAction({
                        name: "mpdOpen",
                        displayName: t(OCA.Dashvideoplayer.AppName, "Play video 1"),
                        mime: attr.mime,
                        permissions: OC.PERMISSION_READ | OC.PERMISSION_UPDATE,
                        icon: function () {
                            return OC.imagePath(OCA.Dashvideoplayer.AppName, "app");
                        },
                        iconClass: "icon-mpd",
                        actionHandler: function (fileName, context) {
                            var fileInfoModel =
                                context.fileInfoModel ||
                                context.fileList.getModelForFile(fileName);
                            OCA.Dashvideoplayer.OpenPlayer(
                                fileInfoModel.id,
                                OC.joinPaths(context.dir, fileName)
                            );
                        }
                    });

                    if (
                        attr.mime == "application/dash+xml" ||
                        attr.mime == "application/vnd.apple.mpegurl"
                    ) {
                        fileList.fileActions.setDefault(attr.mime, "mpdOpen");
                    }
                }
            };

            OCA.Dashvideoplayer.GetSettings(registerfunc);
        }
    };

    OCA.Dashvideoplayer.DisplayError = function (error) {
        $("#app").text(error).addClass("error");
    };

    var getFileExtension = function (fileName) {
        var extension = fileName
            .substr(fileName.lastIndexOf(".") + 1)
            .toLowerCase();
        return extension;
    };

    var initPage = function () {
        console.log("init.ispubic: ", $("#isPublic").val());
        if ($("#isPublic").val() === "1" && !$("#filestable").length) {
            var fileName = $("#filename").val();
            var mimeType = $("#mimetype").val();
            var extension = getFileExtension(fileName);

            var initSharedButton = function () {
                var formats = OCA.Dashvideoplayer.Mimes;
                var config = formats[extension];
                if (!config) {
                    return;
                }

                var button = document.createElement("a");
                button.href = OC.generateUrl(
                    "apps/" +
                    OCA.Dashvideoplayer.AppName +
                    "/s/" +
                    encodeURIComponent($("#sharingToken").val())
                );
                button.className = "button";
                button.innerText = t(OCA.Dashvideoplayer.AppName, "Play video");
                $("#preview").append(button);
            };

            OCA.Dashvideoplayer.GetSettings(initSharedButton);
        } else {
            OC.Plugins.register("OCA.Files.FileList", OCA.Dashvideoplayer.FileList);
        }
    };

    initPage();
})(OCA);

/*
 * A little bit of a hack - changing file icon...
 */
$(document).ready(function () {
    PluginDashvideoplayer_ChangeIconsNative = function () {
        $("#filestable")
            .find("tr[data-type=file]")
            .each(function () {
                if ($(this).attr("data-mime") == "application/dash+xml" || $(this).attr("data-mime") == "application/vnd.apple.mpegurl" && $(this).find("div.thumbnail").length > 0) {
                    if ($(this).find("div.thumbnail").hasClass("icon-mpd") == false) {
                        $(this).find("div.thumbnail").addClass("icon icon-mpd");
                    }
                }
            });
    };

    if ($("#filesApp").val()) {
        $("#app-content-files")
            .add("#app-content-extstoragemounts")
            .on("changeDirectory", function (e) {
                if (OCA.AppSettings == null) return;
                PluginDashvideoplayer_ChangeIconsNative();
            })
            .on("fileActionsReady", function (e) {
                if (OCA.AppSettings == null) return;
                PluginDashvideoplayer_ChangeIconsNative();
            });
    }
});
