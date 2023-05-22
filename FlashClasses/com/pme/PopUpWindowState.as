// An enumeration of the possible States that a Popup Window can have.
// this can be useful to track when we want to know if a pop-up window is fading in, or fading out.
// During this period of transition the application should know not to jerk the window in another direction or whatever.
class com.pme.PopUpWindowState {
	public static var Down:Number = 1;
	public static var Up:Number = 2;
	public static var Rising:Number = 3;
	public static var Falling:Number = 4;
}
