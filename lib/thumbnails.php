<?php
function generate_image_thumbnail(string $src_path, string $dst_path, int $width, int $height)
{
    if ($src_path == "") {
        return -2;
    }

    $input_path = escapeshellarg($src_path);
    $output_path = escapeshellarg($dst_path);

    $result_code = null;

    exec(command: "magick $input_path -resize {$width}x{$height} -loop 0 $output_path", result_code: $result_code);

    return $result_code;
}

function generate_video_thumbnail(string $src_path, string $folder_path, string $dst_path, int $width, int $height)
{
    if ($src_path == "") {
        return -2;
    }

    if (!is_dir($folder_path) && !mkdir($folder_path, 0777, true)) {
        return -3;
    }

    $input_path = escapeshellarg($src_path);
    $output_path = escapeshellarg($dst_path);

    $ffmpeg_command = "ffmpeg -i $input_path -vf \"fps=4,scale=320:-1:flags=lanczos\" -t 10 $folder_path/frames_%04d.png 2>&1";
    $magick_command = "magick $folder_path/frames_*.png -loop 0 -delay 60 -resize {$width}x{$height} $output_path 2>&1";

    exec($ffmpeg_command, $ffmpeg_output, $ffmpeg_result_code);
    exec($magick_command, $magick_output, $magick_result_code);

    array_map('unlink', array_filter((array) glob("$folder_path/*.*")));
    rmdir($folder_path);

    return $ffmpeg_result_code === 0 && $magick_result_code === 0 ? 0 : -1;
}