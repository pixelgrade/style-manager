import { Fragment } from "react";

const elements = [ {
  children: 'Display Heading',
  id: 'display_font',
}, {
  children: 'Main Heading One',
  id: 'heading_1_font',
}, {
  children: 'Secondary Heading',
  id: 'heading_2_font',
}, {
  children: 'Heading Three',
  id: 'heading_3_font',
}, {
  children: 'Heading Four',
  id: 'heading_4_font',
}, {
  children: 'Heading Five & Six',
  id: 'heading_5_font',
}, {
  children: 'Primary Button',
  id: 'buttons_font',
}, {
  children: 'Opening paragraphs often deserve some form of decorative type treatment to help draw the reader in. These special type treatments serve to mark a clear beginning to an article.',
  id: 'lead_font',
}, {
  children: (
    <Fragment>
      <p>Paragraphs only need enough space below them to let the reader know they are starting on a new paragraph. Any more space than that is distracting and breaks up the flow of reading. White space is important, but you don’t want huge gaps all down your page.</p>
      <p>Typography is more than just what fonts you use. Typography is everything that has to do with how the text looks—such as font size, line length, color, and even more subtle things like the whitespace around a text. Good typography sets the tone of your written message and helps to reinforce its meaning and context.</p>
    </Fragment>
  ),
  id: 'body_font',
}, {
  children: (
    <p>Discover our story</p>
  ),
  id: 'accent_font',
} ];

export default elements;
