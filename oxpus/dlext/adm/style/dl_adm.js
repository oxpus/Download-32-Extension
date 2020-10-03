function help_popup(help_key, param) {
	$.ajax({
        url: dl_help_path + '&help_key=' + help_key + '&value=' + param,
        type: "GET",
        success: function (data) { AJAXDLHelpDisplay(data); }
    });
}
function AJAXDLHelpDisplay(data) {
	var obj = $.parseJSON( data );
    $("#dl_help_title").html(obj.title);
    $("#dl_help_option").html(obj.option);
    $("#dl_help_string").html(obj.string);

    $("#dl_help_popup").fadeIn("fast");
    $("#dl_help_bg").css("opacity", "0.7");
    $("#dl_help_bg").fadeIn("fast");

    $(".dl_help_close").click(function() {
    	$("#dl_help_popup").fadeOut("fast");
    	$("#dl_help_bg").fadeOut("fast");
	});
}
