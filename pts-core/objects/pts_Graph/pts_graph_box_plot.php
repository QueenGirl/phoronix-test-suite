<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008 - 2016, Phoronix Media
	Copyright (C) 2008 - 2016, Michael Larabel

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class pts_graph_box_plot extends pts_graph_horizontal_bars
{
	protected function render_graph_bars()
	{
		$bar_count = count($this->results);
		$separator_height = ($a = (6 - (floor($bar_count / 2) * 2))) > 0 ? $a : 0;
		$bar_height = floor(($this->i['identifier_height'] - ($this->is_multi_way_comparison ? 4 : 0) - $separator_height - ($bar_count * $separator_height)) / $bar_count);
		$this->i['graph_max_value'] = $this->i['graph_max_value'] != 0 ? $this->i['graph_max_value'] : 1;
		$work_area_width = $this->i['graph_left_end'] - $this->i['left_start'];

		$group_offsets = array();
		$id_offsets = array();
		$g_bars = $this->svg_dom->make_g(array('stroke' => self::$c['color']['body_light'], 'stroke-width' => 1));
		foreach($this->results as $identifier => &$group)
		{
			$paint_color = $this->get_paint_color($identifier);
			foreach($group as &$buffer_item)
			{
				$values = $buffer_item->get_result_value();
				$values = explode(',', $values);
				if(empty($values) || count($values) < 2)
				{
					$values = $buffer_item->get_result_raw();
					$values = explode(':', $values);
				}

				if(empty($values) || count($values) < 2)
				{
					continue;
				}

				if(isset($values[10]))
				{
					// Ignore any zeros at the start
					if($values[0] == 0 && $values[5] != 0)
					{
						$j = 0;
						while($values[$j] == 0)
						{
							unset($values[$j]);
							$j++;
						}
					}
					// Ignore any zeros at the end
					if($values[(count($values) - 1)] == 0 && $values[(count($values) - 5)] != 0)
					{
						$j = count($values) - 1;
						while($values[$j] == 0)
						{
							unset($values[$j]);
							$j--;
						}
					}
				}

				$i_o = $this->calc_offset($group_offsets, $identifier);
				$i = $this->calc_offset($id_offsets, $buffer_item->get_result_identifier());
				$px_bound_top = $this->i['top_start'] + ($this->is_multi_way_comparison ? 5 : 0) + ($this->i['identifier_height'] * $i) + ($bar_height * $i_o) + ($separator_height * ($i_o + 1));
				$px_bound_bottom = $px_bound_top + $bar_height;
				$middle_of_bar = $px_bound_top + ($bar_height / 2);

				$avg_value = round(array_sum($values) / count($values), 2);
				$whisker_bottom = pts_math::find_percentile($values, 0.02);
				$whisker_top = pts_math::find_percentile($values, 0.98);
				$median = pts_math::find_percentile($values, 0.5);

				$unique_values = array_unique($values);
				$min_value = round(min($unique_values), 2);
				$max_value = round(max($unique_values), 2);

				$stat_value = 'Min: ' . $min_value . ' / Avg: ' . $avg_value . ' / Max: ' . $max_value;
				$title_tooltip = $buffer_item->get_result_identifier() . ': ' . $stat_value;

				$value_end_left = $this->i['left_start'] + max(1, round(($whisker_bottom / $this->i['graph_max_value']) * $work_area_width));
				$value_end_right = $this->i['left_start'] + round(($whisker_top / $this->i['graph_max_value']) * $work_area_width);
				$box_color = in_array($buffer_item->get_result_identifier(), $this->value_highlights) ? self::$c['color']['highlight'] : $paint_color;

				$this->svg_dom->draw_svg_line($value_end_left, $middle_of_bar, $value_end_right, $middle_of_bar, $box_color, 2, array('xlink:title' => $title_tooltip));
				$this->svg_dom->draw_svg_line($value_end_left, $px_bound_top, $value_end_left, $px_bound_bottom, self::$c['color']['notches'], 2, array('xlink:title' => $title_tooltip));
				$this->svg_dom->draw_svg_line($value_end_right, $px_bound_top, $value_end_right, $px_bound_bottom, self::$c['color']['notches'], 2, array('xlink:title' => $title_tooltip));

				$box_left = $this->i['left_start'] + round((pts_math::find_percentile($values, 0.25) / $this->i['graph_max_value']) * $work_area_width);
				$box_middle = $this->i['left_start'] + round(($median / $this->i['graph_max_value']) * $work_area_width);
				$box_right = $this->i['left_start'] + round((pts_math::find_percentile($values, 0.75) / $this->i['graph_max_value']) * $work_area_width);

				$this->svg_dom->add_element('rect', array('x' => $box_left, 'y' => $px_bound_top, 'width' => ($box_right - $box_left), 'height' => $bar_height, 'fill' => $box_color, 'xlink:title' => $title_tooltip), $g_bars);
				$this->svg_dom->draw_svg_line($box_middle, $px_bound_top, $box_middle, $px_bound_bottom, self::$c['color']['notches'], 2, array('xlink:title' => $title_tooltip));

				$this->svg_dom->add_text_element($stat_value, array('x' => ($this->i['left_start'] - 5), 'y' => ceil($px_bound_top + ($bar_height * 0.8) + 6), 'font-size' => ($this->i['identifier_size'] - 2), 'fill' => self::$c['color']['text'], 'text-anchor' => 'end'));

				foreach($unique_values as &$val)
				{
					if(($val < $whisker_bottom || $val > $whisker_top) && $val > 0.1)
					{
						$this->svg_dom->draw_svg_circle($this->i['left_start'] + round(($val / $this->i['graph_max_value']) * $work_area_width), $middle_of_bar, 1, self::$c['color']['notches']);
					}
				}
			}
		}

		// write a new line along the bottom since the draw_rectangle_with_border above had written on top of it
		$this->svg_dom->draw_svg_line($this->i['left_start'], $this->i['graph_top_end'], $this->i['graph_left_end'], $this->i['graph_top_end'], self::$c['color']['notches'], 1);
	}
	public function render_graph_dimensions()
	{
		parent::render_graph_dimensions();
		$longest_sub_identifier_width = self::text_string_width('Min: ' . $this->i['graph_max_value'] . ' / Avg: XX / Max: ' . $this->i['graph_max_value'], $this->i['identifier_size']);
		$this->i['left_start'] = max($this->i['left_start'], $longest_sub_identifier_width);
	}
	protected function maximum_graph_value()
	{
		$max = 0;
		foreach($this->test_result->test_result_buffer->buffer_items as &$buffer_item)
		{
			$val = $buffer_item->get_result_value();
			if(strpos($val, ','))
			{
				$val = max(explode(',', $val));
			}
			$raw = explode(':', $buffer_item->get_result_raw());
			if(empty($raw) || count($raw) < 2)
			{
				$raw = explode(',', $buffer_item->get_result_raw());
			}

			$max = max($max, $val, max($raw));
		}

		$maximum = (ceil(round($max * 1.04) / $this->i['mark_count']) + 1) * $this->i['mark_count'];
		$maximum = round(ceil($maximum / $this->i['mark_count']), (0 - strlen($maximum) + 2)) * $this->i['mark_count'];
		return $maximum;
	}
}

?>
